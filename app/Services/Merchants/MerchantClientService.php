<?php

namespace App\Services\Merchants;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Mail\ClientEsimMail;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantClientSubscription;
use App\Services\eSIM\ProviderRouter;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Niche\DeviceCompat;
use App\Support\Niche\LpaActivation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Merchant V2 client management. A V2 merchant adds clients (who never log in)
 * and subscribes eSIMs/numbers to them from the MERCHANT's own wallet, at the
 * merchant's existing reseller-lane price (PricingEngine::merchantEsimPrice) —
 * V2 changes only WHO the purchase is for, never the pricing rule. Every action
 * is audited (scoped to the merchant) for a clean dispute trail.
 */
class MerchantClientService
{
    public function __construct(
        private WalletService $wallet,
        private ProviderRouter $router,
        private PricingEngine $pricing,
    ) {}

    /** V2 gate + ownership: only an active V2 merchant may manage clients. */
    private function guardV2(Merchant $merchant): void
    {
        if (! $merchant->isActive() || ! $merchant->isV2()) {
            throw new MerchantException('Client management is a Merchant V2 feature.');
        }
    }

    public function addClient(Merchant $merchant, array $data): MerchantClient
    {
        $this->guardV2($merchant);
        $client = $merchant->clients()->create([
            'name' => trim($data['name']),
            'contact' => $data['contact'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'device' => $data['device'] ?? null,
            'device_os' => $data['device_os'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);
        Auditor::log('merchant.client_added', 'MerchantClient', $client->id, ['merchant_id' => $merchant->id]);

        return $client;
    }

    public function updateClient(Merchant $merchant, MerchantClient $client, array $data): MerchantClient
    {
        $this->assertOwns($merchant, $client);
        $client->update([
            'name' => trim($data['name']),
            'contact' => $data['contact'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'email' => $data['email'] ?? null,
            'device' => $data['device'] ?? null,
            'device_os' => $data['device_os'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        Auditor::log('merchant.client_updated', 'MerchantClient', $client->id, ['merchant_id' => $merchant->id]);

        return $client;
    }

    public function setActive(Merchant $merchant, MerchantClient $client, bool $active): void
    {
        $this->assertOwns($merchant, $client);
        $client->update(['is_active' => $active]);
        Auditor::log($active ? 'merchant.client_reactivated' : 'merchant.client_deactivated', 'MerchantClient', $client->id, ['merchant_id' => $merchant->id]);
    }

    /**
     * Subscribe an eSIM plan to a client, paid from the merchant's wallet at the
     * merchant price. Same money discipline as customer checkout: idempotent
     * debit, double-submit guard, ProviderRouter self-refunds if it can't fulfil,
     * orphan-charge guard around the order record. Tags the order with the client.
     *
     * @throws MerchantException
     */
    public function assignEsim(Merchant $merchant, MerchantClient $client, EsimPlan $plan, bool $force = false): EsimOrder
    {
        $this->assertOwns($merchant, $client);
        if (! $client->is_active) {
            throw new MerchantException('Reactivate this client before assigning a new eSIM.');
        }

        // Device compatibility gate (Merchant V2): block a KNOWN-incompatible
        // device unless the merchant explicitly overrides. Unknown = allowed.
        $compat = DeviceCompat::check((string) ($client->device_os ?: $client->device));
        if ($compat === false && ! $force) {
            throw new MerchantException("That client's device may not support eSIM. Confirm compatibility, then assign with override.");
        }

        $owner = $merchant->owner;
        $price = round($this->pricing->merchantEsimPrice($plan, $merchant), 4);
        $ref = 'mclient-esim:'.$client->id.':'.$plan->id.':'.Str::uuid();

        try {
            $debit = $this->wallet->debit($owner, $price, 'USD', [
                'reference' => $ref,
                'description' => "eSIM for {$client->name}: {$plan->name}",
            ]);
        } catch (InsufficientBalanceException $e) {
            throw new MerchantException('Your merchant wallet is too low — top up at least $'.number_format($price, 2).'.');
        }

        // Double-submit guard: the debit is idempotent, the provider order is not.
        if (! $debit->wasRecentlyCreated) {
            throw new MerchantException('That purchase is already being processed.');
        }

        try {
            $result = $this->router->orderPlan((string) $plan->id, $owner, 'USD', $price);
        } catch (EsimProviderException $e) {
            // ProviderRouter already refunded the exact charge.
            throw new MerchantException('No provider could fulfil that plan right now — your wallet was refunded.');
        }

        try {
            $order = EsimOrder::create([
                'user_id' => $owner->id,
                'merchant_client_id' => $client->id,
                'plan_id' => $plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference') ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid') ?? data_get($result->payload, 'esims.0.iccid'),
                'qr_code_url' => data_get($result->payload, 'qr_code') ?? data_get($result->payload, 'qrCodeUrl'),
                'lpa_string' => LpaActivation::fromPayload($result->payload),
                'status' => 'processing',
                'price_charged' => $price,
                'wholesale_cost' => $result->cost,
                'currency' => 'USD',
            ]);
        } catch (Throwable $e) {
            // Orphan-charge guard: refund the merchant since we couldn't record it.
            $this->wallet->refund($owner, $price, 'USD', ['reference' => 'refund:'.$ref, 'description' => 'eSIM order could not be recorded']);
            throw new MerchantException('Something went wrong saving that order — your wallet was refunded.');
        }

        // Record/refresh the subscription lifecycle (type, expiry countdown).
        $expiresAt = $plan->validity_days ? now()->addDays((int) $plan->validity_days) : null;
        MerchantClientSubscription::create([
            'merchant_id' => $merchant->id,
            'merchant_client_id' => $client->id,
            'esim_order_id' => $order->id,
            'plan_id' => $plan->id,
            'esim_type' => $plan->has_voice ? MerchantClientSubscription::TYPE_CONNECT : MerchantClientSubscription::TYPE_DATA,
            'status' => MerchantClientSubscription::STATUS_ACTIVE,
            'activated_at' => now(),
            'expires_at' => $expiresAt,
            'renewal_price' => $price,
        ]);

        Auditor::log('merchant.client_esim_assigned', 'MerchantClient', $client->id, [
            'merchant_id' => $merchant->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        ]);

        return $order;
    }

    /**
     * Turn on auto-renewal for a subscription: RESERVE the next renewal amount
     * from the merchant wallet (earmarked, non-reusable). Per the platform rule
     * this is intentionally one-way — the reservation is only ever freed by the
     * due-date run (settled on success, released if provisioning fails). Throws
     * if the wallet's spendable balance can't cover the reserve.
     */
    public function enableAutoRenew(Merchant $merchant, MerchantClientSubscription $subscription): void
    {
        $this->assertOwnsSubscription($merchant, $subscription);
        if ($subscription->auto_renew) {
            return; // already reserved — idempotent
        }
        if ($subscription->status === MerchantClientSubscription::STATUS_DISABLED) {
            throw new MerchantException('This subscription is disabled.');
        }

        $price = round((float) $subscription->renewal_price, 4);
        if ($price <= 0) {
            throw new MerchantException('This subscription has no renewal price to reserve.');
        }

        try {
            $this->wallet->reserve($merchant->owner, $price);
        } catch (InsufficientBalanceException $e) {
            throw new MerchantException('Your spendable merchant balance is too low to lock $'.number_format($price, 2).' for auto-renewal.');
        }

        $subscription->update([
            'auto_renew' => true,
            'reserve_reference' => 'sub-reserve:'.$subscription->id,
        ]);
        Auditor::log('merchant.autorenew_reserved', 'MerchantClientSubscription', $subscription->id, [
            'merchant_id' => $merchant->id, 'amount' => $price,
        ]);
    }

    /**
     * Settle a due auto-renewal (called by the scheduled command). Frees the
     * earmark, then re-provisions through the SAME money-safe path as a normal
     * assign (debit → provider → refund-on-failure). On success the subscription
     * is renewed; on failure the merchant keeps the funds (the earmark is freed
     * by the release, and the failed provider order self-refunds any debit) —
     * the only route by which a reserved auto-renewal is returned.
     */
    public function renewDueSubscription(MerchantClientSubscription $subscription): bool
    {
        $merchant = $subscription->merchant;
        $client = $subscription->client;
        $plan = $subscription->plan;

        if (! $merchant || ! $client || ! $plan || ! $subscription->auto_renew) {
            return false;
        }

        // Free the earmark so the real debit can draw on those funds.
        if ($subscription->reserve_reference) {
            $this->wallet->release($merchant->owner, (float) $subscription->renewal_price);
        }
        $subscription->update(['auto_renew' => false, 'reserve_reference' => null]);

        try {
            $order = $this->assignEsim($merchant, $client, $plan, force: true);
        } catch (MerchantException $e) {
            // Provisioning failed → funds are back with the merchant (released +
            // provider self-refund). Leave the subscription for manual attention.
            Auditor::log('merchant.autorenew_failed', 'MerchantClientSubscription', $subscription->id, [
                'merchant_id' => $merchant->id, 'reason' => $e->getMessage(),
            ]);

            return false;
        }

        // assignEsim created a fresh subscription row; retire the old one.
        $subscription->update(['status' => MerchantClientSubscription::STATUS_EXPIRED, 'esim_order_id' => $order->id]);
        Auditor::log('merchant.autorenew_settled', 'MerchantClientSubscription', $subscription->id, [
            'merchant_id' => $merchant->id, 'order_id' => $order->id,
        ]);

        return true;
    }

    /**
     * Deliver a client's eSIM (QR + activation code + install steps) to the
     * client over their chosen channel(s): email, WhatsApp, or both. Email carries
     * the scannable QR inline; WhatsApp carries the tap-to-install code + steps
     * (a wa.me message can't attach an image). Returns what was sent so the UI can
     * surface the WhatsApp forward link. Never sends before the eSIM is ready.
     *
     * @return array{email_sent: bool, whatsapp_link: ?string}
     *
     * @throws MerchantException
     */
    public function deliverEsim(Merchant $merchant, MerchantClientSubscription $subscription, string $channel): array
    {
        $this->assertOwnsSubscription($merchant, $subscription);
        if (! in_array($channel, ['email', 'whatsapp', 'both'], true)) {
            throw new MerchantException('Choose how to send it: email, WhatsApp, or both.');
        }

        $order = $subscription->order;
        $client = $subscription->client;
        if (! $order || ! $client) {
            throw new MerchantException('That eSIM could not be found.');
        }
        if (! $order->isDeliverable()) {
            throw new MerchantException("This eSIM is still provisioning — its activation code isn't ready yet. Try again in a moment.");
        }

        $brand = $merchant->business_name ?: 'Your provider';
        $planName = $subscription->plan?->name ?? $order->plan?->name ?? 'eSIM plan';
        $lpa = $order->lpa_string;
        $result = ['email_sent' => false, 'whatsapp_link' => null];

        if (in_array($channel, ['email', 'both'], true)) {
            if (blank($client->email)) {
                throw new MerchantException('Add an email to this client to send it by email.');
            }
            Mail::to($client->email)->send(new ClientEsimMail(
                clientName: (string) $client->name,
                brand: $brand,
                planName: (string) $planName,
                lpa: $lpa,
                qrCodeUrl: $order->qr_code_url,
                universalLink: $lpa ? LpaActivation::universalLink($lpa) : null,
                steps: LpaActivation::steps(),
            ));
            $result['email_sent'] = true;
        }

        if (in_array($channel, ['whatsapp', 'both'], true)) {
            if (blank($client->whatsapp)) {
                throw new MerchantException('Add a WhatsApp number to this client to send it on WhatsApp.');
            }
            $result['whatsapp_link'] = $client->whatsappLink($this->esimWhatsappText($brand, $client->name, $planName, $lpa));
        }

        Auditor::log('merchant.client_esim_delivered', 'MerchantClientSubscription', $subscription->id, [
            'merchant_id' => $merchant->id, 'channel' => $channel,
        ]);

        return $result;
    }

    /** Branded WhatsApp install message (tap-to-install link + manual code). */
    private function esimWhatsappText(string $brand, string $clientName, string $planName, ?string $lpa): string
    {
        $lines = [
            "*{$brand} — your eSIM is ready*",
            '',
            "Hi {$clientName}, your {$planName} eSIM is set up. Install it before you travel:",
        ];
        if ($lpa && ($link = LpaActivation::universalLink($lpa))) {
            $lines[] = '';
            $lines[] = "iPhone (one tap): {$link}";
        }
        if ($lpa) {
            $lines[] = '';
            $lines[] = 'Manual code (Android / older iPhone):';
            $lines[] = $lpa;
        }
        $lines[] = '';
        $lines[] = 'Add it under Settings → Mobile/Cellular → Add eSIM → Enter details manually.';
        $lines[] = 'Keep this code private — it activates only once.';

        return implode("\n", $lines);
    }

    /** Disable a client's eSIM (non-payment) — stop it counting as active. */
    public function disableEsim(Merchant $merchant, MerchantClientSubscription $subscription): void
    {
        $this->assertOwnsSubscription($merchant, $subscription);
        // If it was earmarked for auto-renew, free the funds (this is a merchant
        // action on a non-paying client, distinct from the one-way reserve rule
        // which governs the auto-charge itself).
        if ($subscription->auto_renew && $subscription->reserve_reference) {
            $this->wallet->release($merchant->owner, (float) $subscription->renewal_price);
        }
        $subscription->update([
            'status' => MerchantClientSubscription::STATUS_DISABLED,
            'auto_renew' => false,
            'reserve_reference' => null,
        ]);
        Auditor::log('merchant.client_esim_disabled', 'MerchantClientSubscription', $subscription->id, [
            'merchant_id' => $merchant->id,
        ]);
    }

    private function assertOwnsSubscription(Merchant $merchant, MerchantClientSubscription $subscription): void
    {
        $this->guardV2($merchant);
        if ((int) $subscription->merchant_id !== (int) $merchant->id) {
            throw new MerchantException('That subscription is not yours.');
        }
    }

    private function assertOwns(Merchant $merchant, MerchantClient $client): void
    {
        $this->guardV2($merchant);
        if ((int) $client->merchant_id !== (int) $merchant->id) {
            throw new MerchantException('That client is not yours.');
        }
    }
}
