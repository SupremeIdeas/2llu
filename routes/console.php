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

// Ping provider wallets and alert on low balance (Section 17.2).
Schedule::command('providers:health-check')->everyFifteenMinutes()->withoutOverlapping();

// Refresh eSIM catalogues + recompute retail via the PricingEngine.
Schedule::command('esim:sync')->dailyAt('03:00')->withoutOverlapping();
