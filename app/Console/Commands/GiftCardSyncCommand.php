<?php

namespace App\Console\Commands;

use App\Services\GiftCards\GiftCardCatalogueSyncService;
use Illuminate\Console\Command;

/**
 * Sync the Naara Gift catalogue from Reloadly (primary) + Zendit (failover).
 * `giftcards:sync` does both; `giftcards:sync reloadly` does one.
 */
class GiftCardSyncCommand extends Command
{
    protected $signature = 'giftcards:sync {provider? : reloadly|zendit (default: both)}';

    protected $description = 'Sync gift-card products from Reloadly + Zendit into the Naara Gift catalogue';

    public function handle(GiftCardCatalogueSyncService $sync): int
    {
        $providers = $this->argument('provider') ? [$this->argument('provider')] : ['reloadly', 'zendit'];

        foreach ($providers as $provider) {
            $n = $sync->sync($provider);
            $this->info("{$provider}: synced {$n} product(s).");
        }

        return self::SUCCESS;
    }
}
