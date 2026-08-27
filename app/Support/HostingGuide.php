<?php

namespace App\Support;

/**
 * Dual-hosting setup guidance for the admin System Health page. NaaraSim ships
 * to run on BOTH a VPS (Cloudways-style: Redis + Horizon persistent workers) and
 * shared hosting (Namecheap/cPanel: a database queue drained by a one-minute
 * cron). This turns "how do I wire the background layer on THIS host, in the
 * right order?" into copy-paste steps, keyed to the environment actually
 * detected, with the concrete app path + PHP binary filled in.
 */
class HostingGuide
{
    /** 'vps' when the queue is Redis (Horizon), else 'shared' (database cron drain). */
    public static function mode(): string
    {
        return QueueHealth::driver() === 'redis' ? 'vps' : 'shared';
    }

    /** Absolute app path for the cron line (real on the host, copy-paste ready). */
    public static function appPath(): string
    {
        return base_path();
    }

    /** The PHP binary running this process (cPanel often needs the full path). */
    public static function phpBinary(): string
    {
        return PHP_BINARY ?: 'php';
    }

    /** The one scheduler cron both hosting modes need, minute-by-minute. */
    public static function cronLine(): string
    {
        return '* * * * * cd '.self::appPath().' && '.self::phpBinary().' artisan schedule:run >> /dev/null 2>&1';
    }

    /**
     * Ordered setup steps per hosting mode. Each step is [title, detail, code?].
     *
     * @return array{shared: list<array{0:string,1:string,2?:string}>, vps: list<array{0:string,1:string,2?:string}>}
     */
    public static function steps(): array
    {
        $cron = self::cronLine();
        $php = self::phpBinary();

        return [
            'shared' => [
                ['1. Set the drivers in .env',
                    'Shared hosting has no Redis and no long-running worker, so use the database for the queue.',
                    "QUEUE_CONNECTION=database\nCACHE_STORE=database\nSESSION_DRIVER=database"],
                ['2. Migrate',
                    'Creates the jobs / failed_jobs / sessions tables the drivers above need.',
                    "$php artisan migrate --force"],
                ['3. Add ONE cron job (cPanel → Cron Jobs), every minute',
                    'This single cron runs the scheduler, which itself drains the queue every minute (queue:work --stop-when-empty). No separate worker to keep alive.',
                    $cron],
                ['4. Verify',
                    'Within ~1 minute the "Scheduled tasks" list below should show recent runs and the queue backlog should drain. If tasks stay overdue, the cron is not firing — re-check the cron path + PHP binary.',
                    null],
            ],
            'vps' => [
                ['1. Set the drivers in .env',
                    'A VPS runs Redis + Horizon, so point the queue (and cache/session) at Redis.',
                    "QUEUE_CONNECTION=redis\nCACHE_STORE=redis\nSESSION_DRIVER=redis\nREDIS_HOST=127.0.0.1\nREDIS_PORT=6379\nREDIS_PASSWORD=null"],
                ['2. Migrate',
                    'Still needed for failed_jobs + app tables.',
                    "$php artisan migrate --force"],
                ['3. Add the scheduler cron (Cloudways → Cron Job Management), every minute',
                    'Same one-minute scheduler cron as shared hosting — it runs the daily/periodic tasks. On Redis it does NOT drain the queue (Horizon does that), so it stays light.',
                    $cron],
                ['4. Run Horizon as a persistent worker',
                    'Add a Supervisor/systemd process (Cloudways: Application Settings → Supervisor) that keeps Horizon alive and restarts it on boot. Redeploys should call horizon:terminate so workers reload the new code.',
                    "$php artisan horizon"],
                ['5. Verify',
                    'The "Workers & background jobs" panel above should show Horizon = Running with a live worker count. If it shows Stopped, the Supervisor process is not up — jobs will queue but never process, and admins get alerted automatically.',
                    null],
            ],
        ];
    }
}
