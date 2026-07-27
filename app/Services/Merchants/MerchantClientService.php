<?php

namespace App\Services\Merchants;

use App\Exceptions\InsufficientBalanceException;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Services\eSIM\EsimProviderException;
use App\Services\eSIM\ProviderRouter;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\Niche\LpaActivation;
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
    ) {
    }

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
            'device' => $data['device'] ?? null,
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
            'device' => $data['device'] ?? null,
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
    public function assignEsim(Merchant $merchant, MerchantClient $client, EsimPlan $plan): EsimOrder
    {
        $this->assertOwns($merchant, $client);
        if (! $client->is_active) {
            throw new MerchantException('Reactivate this client before assigning a new eSIM.');
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

        Auditor::log('merchant.client_esim_assigned', 'MerchantClient', $client->id, [
            'merchant_id' => $merchant->id, 'plan_id' => $plan->id, 'order_id' => $order->id,
        ]);

        return $order;
    }

    private function assertOwns(Merchant $merchant, MerchantClient $client): void
    {
        $this->guardV2($merchant);
        if ((int) $client->merchant_id !== (int) $merchant->id) {
            throw new MerchantException('That client is not yours.');
        }
    }
}
