<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Provider health across the WHOLE eSIM + number stack (BUILD-5 §2). The old
 * providers:health-check only pinged three wallet-funded providers; this covers
 * every Active provider in both stacks.
 *
 * The probe IS the reachability check: calling a provider's balance endpoint
 * hits its live API, so a provider that is down/erroring surfaces as `down`
 * with the error, not silently. Providers with no wallet-balance concept
 * (Twilio/Telnyx — permanent/voice) are reported `configured` with no live
 * balance probe rather than faked.
 *
 * Results are cached under the same key the admin dashboard already reads, so
 * the existing "Provider wallets" widget keeps working and simply shows more.
 */
class ProviderHealth
{
    public const CACHE_KEY = 'providers:health';

    /**
     * provider => [container binding, low-balance Setting key, stack].
     * The probe method is auto-detected (getBalance for eSIM, balance for
     * number providers) so we never call a method a service doesn't have.
     *
     * @var array<string, array{0:string,1:?string,2:string}>
     */
    private const PROVIDERS = [
        // eSIM stack — every provider implements EsimProviderInterface::getBalance().
        'esimgo' => ['esim.esimgo', 'pricing.low_balance_alert.esimgo', 'esim'],
        'airalo' => ['esim.airalo', 'pricing.low_balance_alert.airalo', 'esim'],
        'quibity' => ['esim.quibity', 'pricing.low_balance_alert.quibity', 'esim'],
        'zendit' => ['esim.zendit', 'pricing.low_balance_alert.zendit', 'esim'],
        'oneglobal' => ['esim.oneglobal', 'pricing.low_balance_alert.oneglobal', 'esim'],
        'montymobile' => ['esim.montymobile', 'pricing.low_balance_alert.montymobile', 'esim'],
        'gigs' => ['esim.gigs', 'pricing.low_balance_alert.gigs', 'esim'],
        // Number stack — SMS/OTP providers implement SmsProviderInterface::balance().
        'getatext' => ['number.getatext', 'pricing.low_balance_alert.getatext', 'number'],
        'fivesim' => ['number.fivesim', 'pricing.low_balance_alert.fivesim', 'number'],
        'herosms' => ['number.herosms', 'pricing.low_balance_alert.herosms', 'number'],
        'virtsms' => ['number.virtsms', 'pricing.low_balance_alert.virtsms', 'number'],
        // Permanent/voice — no prepaid wallet balance to read; reachability only.
        'twilio' => ['number.twilio', null, 'number'],
        'telnyx' => ['number.telnyx', null, 'number'],
    ];

    /**
     * Probe every provider and return a status map. Shape per provider:
     * ['status' => ok|low|down|configured|coming_soon, 'balance' => ?float,
     *  'stack' => esim|number, 'error' => ?string, 'checked_at' => string,
     *  'last_success_at' => ?string].
     *
     * @return array<string, array<string, mixed>>
     */
    public function checkAll(): array
    {
        $previous = $this->cached();
        $health = [];

        foreach (self::PROVIDERS as $provider => [$binding, $alertKey, $stack]) {
            $health[$provider] = $this->probe($provider, $binding, $alertKey, $stack, $previous[$provider] ?? []);
        }

        Cache::put(self::CACHE_KEY, $health, now()->addMinutes(30));

        return $health;
    }

    /** @param array<string, mixed> $prev */
    private function probe(string $provider, string $binding, ?string $alertKey, string $stack, array $prev): array
    {
        $now = now()->toDateTimeString();
        $base = ['stack' => $stack, 'balance' => null, 'checked_at' => $now,
            'last_success_at' => $prev['last_success_at'] ?? null];

        if (! ProviderStatus::isActive($provider)) {
            return ['status' => 'coming_soon'] + $base;
        }

        $service = app($binding);
        $method = method_exists($service, 'getBalance') ? 'getBalance'
            : (method_exists($service, 'balance') ? 'balance' : null);

        // No wallet-balance concept (e.g. Twilio/Telnyx): it's configured, but we
        // don't fake a live probe we can't do cheaply.
        if ($method === null) {
            return ['status' => 'configured'] + $base;
        }

        try {
            $balance = (float) $service->{$method}();
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()] + $base;
        }

        $threshold = $alertKey !== null ? (float) Setting::getValue($alertKey, 0) : 0.0;
        $low = $threshold > 0 && $balance < $threshold;

        return [
            'status' => $low ? 'low' : 'ok',
            'balance' => $balance,
            'stack' => $stack,
            'checked_at' => $now,
            'last_success_at' => $now,
        ] + ($low ? ['threshold' => $threshold] : []);
    }

    /** @return array<string, array<string, mixed>> */
    public function cached(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    /** Providers whose last probe put them in a bad state (down or low). */
    public function unhealthy(): array
    {
        return array_filter($this->cached(), fn ($h) => in_array($h['status'] ?? '', ['down', 'low'], true));
    }
}
