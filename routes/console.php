<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Scheduled tasks (blueprint Section 20). A single cron entry drives all of
 | these on the server:
 |   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
 */

// Shared-hosting queue drain (blueprint Section 20). On shared cPanel there is
// no Redis and no long-running worker, so every external API job (money paths
// included, money rule 8) sits in the `jobs` table until we drain it. The one
// schedule:run cron above triggers this each minute; --stop-when-empty keeps it
// short-lived so it never becomes a runaway process. On a VPS the queue is
// Redis + Horizon, so this drain is skipped entirely.
if (config('queue.default') === 'database') {
    // --tries=1 is the safe floor: money jobs never blind-retry (money rule 7).
    // Jobs that DO want retries opt in via their own $tries (e.g. the catalogue
    // sync), which takes precedence over this worker default.
    Schedule::command('queue:work --stop-when-empty --tries=1 --max-time=50')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
}

// Ping provider wallets and alert on low balance (Section 17.2).
Schedule::command('providers:health-check')->everyFifteenMinutes()->withoutOverlapping();

// Charge permanent-number (Naara Line) monthly subscriptions + release lapsed ones.
Schedule::command('virtual:renew')->dailyAt('04:00')->withoutOverlapping();

// Refresh eSIM catalogues + recompute retail via the PricingEngine.
Schedule::command('esim:sync')->dailyAt('03:00')->withoutOverlapping();

// Refresh the number country + service catalogue from the providers so the
// storefront always lists everything they support (blueprint Section 12).
Schedule::command('numbers:catalogue-sync')->weekly()->sundays()->at('03:30')->withoutOverlapping();

// Nightly encrypted database backup + cleanup of old archives (Section 28).
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping();

// Auto-promote eligible users to Merchant V1 — no-op unless the admin enabled it
// (BUILD-4 §4.3; the default is the manual "Ready to promote" queue).
Schedule::command('merchants:auto-promote')->dailyAt('05:00')->withoutOverlapping();

// Recompute the volume-based payout-gateway ranking (BUILD-4 §8) — cached daily.
Schedule::command('payouts:rank')->dailyAt('05:30')->withoutOverlapping();

// Refresh live currency-display FX rates (localized pricing) — display only.
Schedule::command('fx:sync')->dailyAt('05:00')->withoutOverlapping();
Schedule::command('backup:run --only-db')->dailyAt('02:45')->withoutOverlapping();

// Promote local platform media to Wasabi once cloud keys go live (no-ops
// without keys, so it's safe to run hourly — the migration is automatic).
Schedule::command('media:migrate-to-wasabi')->hourly()->withoutOverlapping();

// Partner profit-share payouts — daily, but each partner is only paid when a
// full weekly/monthly period has elapsed (idempotent per period).
Schedule::command('partners:payout-run')->dailyAt('04:30')->withoutOverlapping();

// Merchant V2 client eSIM control: settle due auto-renewals, expire lapsed
// subscriptions, and alert merchants about upcoming renewals (money-safe).
Schedule::command('merchant:client-subscriptions')->dailyAt('05:30')->withoutOverlapping();

// Naara Gift: sync the gift-card catalogue from Reloadly (primary) + Zendit.
Schedule::command('giftcards:sync')->dailyAt('03:15')->withoutOverlapping();
