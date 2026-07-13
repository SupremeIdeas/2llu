<?php

namespace App\Console\Commands;

use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Support\ProviderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * providers:health-check (blueprint Section 17.2). Pings each wallet-key
 * provider every 15 min (scheduled), stores the balance for the admin
 * dashboard, and fires a low-balance alert when a wallet drops below its
 * configured threshold — so a provider wallet never runs dry silently.
 */
class ProvidersHealthCheckCommand extends Command
{
    protected $signature = 'providers:health-check';

    protected $description = 'Ping provider wallets, cache balances, and alert on low balance';

    /** provider => [container binding, low-balance setting key]. */
    private const WALLET_PROVIDERS = [
        'esimgo' => ['esim.esimgo', 'pricing.low_balance_alert.esimgo'],
        'getatext' => ['number.getatext', 'pricing.low_balance_alert.getatext'],
        'fivesim' => ['number.fivesim', 'pricing.low_balance_alert.fivesim'],
    ];

    public function handle(): int
    {
        $health = [];

        foreach (self::WALLET_PROVIDERS as $provider => [$binding, $alertKey]) {
            if (! ProviderStatus::isActive($provider)) {
                $health[$provider] = ['status' => 'coming_soon', 'balance' => null, 'checked_at' => now()->toDateTimeString()];

                continue;
            }

            try {
                $balance = app($binding)->{$provider === 'esimgo' ? 'getBalance' : 'balance'}();
                $threshold = (float) Setting::getValue($alertKey, 0);
                $low = $threshold > 0 && $balance < $threshold;

                if ($low) {
                    AlertAdminJob::dispatch(
                        code: strtoupper($provider).'_low_balance',
                        message: "{$provider} wallet balance {$balance} is below the alert threshold {$threshold}.",
                        context: ['provider' => $provider, 'balance' => $balance, 'threshold' => $threshold],
                        severity: 'warning',
                    );
                }

                $health[$provider] = [
                    'status' => $low ? 'low' : 'ok',
                    'balance' => $balance,
                    'checked_at' => now()->toDateTimeString(),
                ];
            } catch (\Throwable $e) {
                $health[$provider] = ['status' => 'error', 'balance' => null, 'error' => $e->getMessage(), 'checked_at' => now()->toDateTimeString()];
            }
        }

        Cache::put('providers:health', $health, now()->addMinutes(30));
        $this->info('Provider health checked: '.collect($health)->map(fn ($h, $p) => "$p={$h['status']}")->implode(', '));

        return self::SUCCESS;
    }
}
