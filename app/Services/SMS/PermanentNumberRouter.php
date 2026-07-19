<?php

namespace App\Services\SMS;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\User;
use App\Models\VirtualNumber;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Permanent-number provisioning (Naara Line) — Twilio → Telnyx lane, billed
 * monthly. Money-safety mirrors the eSIM Checkout:
 *   - the FIRST month's retail is debited before we provision; if provisioning
 *     fails the wallet is refunded and the next provider tried,
 *   - if the number is provisioned but the record can't be saved, the number is
 *     released AND the wallet refunded (orphan-charge guard),
 *   - retail always flows through PricingEngine (MarginGuard floor) — a number is
 *     skipped if it can't be sold at cost + minimum profit,
 *   - the provider is never exposed; the user sees only the Model (Naara Line).
 * Recurring monthly charges are handled by RenewVirtualNumbersJob.
 */
class PermanentNumberRouter
{
    /** @var list<string> */
    protected array $lane = ['twilio', 'telnyx'];

    public function __construct(
        private readonly WalletService $wallet,
        private readonly PricingEngine $pricing,
    ) {
    }

    /** Providers in the lane that actually have keys configured. */
    public function configuredLane(): array
    {
        return array_values(array_filter($this->lane, fn ($p) => $this->isConfigured($p)));
    }

    private function isConfigured(string $provider): bool
    {
        return \App\Support\ProviderModels::providerConfigured($provider);
    }

    /**
     * Search the lane for available numbers, priced at RETAIL (never cost). Uses
     * the first configured provider that returns results.
     *
     * @return array{provider: ?string, numbers: array<int, array{number:string, locality:string, monthly_retail:float}>}
     */
    public function search(string $country, array $options = []): array
    {
        foreach ($this->lane as $provider) {
            if (! $this->isConfigured($provider)) {
                continue;
            }
            $svc = app("number.{$provider}");
            $found = $svc->searchNumbers($country, $options);
            if ($found === []) {
                continue;
            }
            $cost = (float) $svc->monthlyCost($country);
            $retail = $this->pricing->calculateSmsRetail($cost, $provider); // MarginGuard-floored

            return [
                'provider' => $provider,
                'numbers' => array_map(fn ($n) => [
                    'number' => $n['number'],
                    'locality' => $n['locality'] ?? '',
                    'monthly_retail' => round($retail, 2),
                ], $found),
            ];
        }

        return ['provider' => null, 'numbers' => []];
    }

    /**
     * Provision a specific number from a specific (server-chosen) provider. The
     * provider MUST come from a prior search() on the server, never the client.
     */
    public function provision(User $user, string $country, string $number, string $provider): VirtualNumber
    {
        if (! in_array($provider, $this->lane, true) || ! $this->isConfigured($provider)) {
            throw new SmsException('That number is no longer available.');
        }

        $svc = app("number.{$provider}");
        $cost = (float) $svc->monthlyCost($country);
        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $retail = round($this->pricing->calculateSmsRetail($cost, $provider), 4);

        // MarginGuard backstop (calculateSmsRetail already floors, but never trust).
        if ($retail < $cost + $minProfit) {
            throw new SmsException('This number can’t be offered right now.');
        }

        $ref = "vnum:{$user->id}:".preg_replace('/\D/', '', $number).':'.now()->timestamp;

        // 1) Charge the first month up-front (never provision without payment).
        try {
            $this->wallet->debit($user, $retail, 'USD', [
                'reference' => $ref,
                'description' => 'Virtual number (first month)',
            ]);
        } catch (InsufficientBalanceException $e) {
            throw $e; // caller shows "top up"
        }

        // 2) Provision at the provider.
        try {
            $bought = $svc->buyNumber($country, ['number' => $number]);
        } catch (Throwable $e) {
            $this->wallet->refund($user, $retail, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Number provisioning failed']);
            Log::warning("PermanentNumberRouter: {$provider} provisioning failed: ".$e->getMessage());
            throw new SmsException('That number could not be reserved — your wallet was refunded.');
        }

        // 3) Persist the subscription. Orphan-charge guard: release + refund on failure.
        try {
            return VirtualNumber::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'phone_number' => $bought['number'],
                'sid' => $bought['provider_ref'],
                'capabilities' => $bought['capabilities'] ?? ['sms' => true, 'voice' => true],
                'monthly_cost' => $cost,
                'monthly_retail' => $retail,
                'status' => 'active',
                'next_billing_date' => now()->addMonthNoOverflow()->toDateString(),
                'provisioned_at' => now(),
            ]);
        } catch (Throwable $e) {
            $svc->releaseNumber($bought['provider_ref']);
            $this->wallet->refund($user, $retail, 'USD', ['reference' => "refund:{$ref}", 'description' => 'Number could not be saved']);
            AlertAdminJob::dispatch(
                code: 'vnum_save_failed',
                message: "Provisioned {$bought['number']} for user {$user->id} but failed to persist; released + refunded.",
                context: ['user_id' => $user->id, 'number' => $bought['number']],
            );
            throw new SmsException('Something went wrong finalising your number — your wallet was refunded.');
        }
    }
}
