<?php

namespace App\Livewire\Admin;

use App\Support\EnvironmentGuard;
use App\Support\SchedulerHealth;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → System Health (HOTFIX §2). One place to answer "is the live cron
 * actually running and the queue draining?" — the confirmed root cause behind
 * "Paystack didn't credit" and "provider health widget is empty". Shows each
 * scheduled task's last run + overdue flag, the queue connection (loudly flagged
 * if sync in production), and the live queue backlog. Super-admin / admin only.
 */
#[Layout('components.layouts.admin')]
class SystemHealth extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function refreshHealth(): void
    {
        // No-op: re-render pulls fresh values. Gives the admin a manual refresh.
        $this->dispatch('nx-toast', type: 'success', message: 'Refreshed.');
    }

    /**
     * Clear the platform's caches. `scope` picks how deep: 'app' clears just the
     * application cache store (safe, instant — the usual "flush cache" the owner
     * asked for); 'all' also clears the compiled config/route/view caches
     * (optimize:clear). Admin-only; safe to run on both VPS and cPanel.
     */
    public function flushCache(string $scope = 'app'): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        try {
            if ($scope === 'all') {
                \Illuminate\Support\Facades\Artisan::call('optimize:clear');
                $msg = 'All caches cleared (application, config, routes, views).';
            } else {
                \Illuminate\Support\Facades\Artisan::call('cache:clear');
                $msg = 'Application cache cleared.';
            }
            $this->dispatch('nx-toast', type: 'success', message: $msg);
        } catch (\Throwable $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Could not clear cache: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.system-health', [
            'tasks' => SchedulerHealth::report(),
            'anyOverdue' => SchedulerHealth::anyOverdue(),
            'queueConnection' => SchedulerHealth::queueConnection(),
            'queueIsSync' => SchedulerHealth::queueIsSync(),
            'queueBacklog' => SchedulerHealth::queueBacklog(),
            'oldestJobAge' => SchedulerHealth::oldestJobAgeSeconds(),
            'envWarnings' => EnvironmentGuard::warnings(),
            // Worker layer (Platform Health): the live Redis/Horizon picture, so
            // "are background jobs actually processing?" is answerable on VPS too.
            'worker' => \App\Support\QueueHealth::workerVerdict(),
            'workerDriver' => \App\Support\QueueHealth::driver(),
            'horizonInstalled' => \App\Support\QueueHealth::horizonInstalled(),
            'horizonActive' => \App\Support\QueueHealth::horizonActive(),
            'redisReachable' => \App\Support\QueueHealth::redisReachable(),
            'pendingByQueue' => \App\Support\QueueHealth::pendingByQueue(),
            'failedCount' => \App\Support\QueueHealth::failedCount(),
            'recentFailed' => \App\Support\QueueHealth::recentFailed(),
            // Recent inbound webhook deliveries (readiness Domain 13/14) — lets an
            // operator confirm a provider (Paystack, Twilio…) is actually calling.
            'webhookDeliveries' => $this->webhookDeliveries(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function webhookDeliveries()
    {
        try {
            return \Illuminate\Support\Facades\DB::table('webhook_deliveries')
                ->latest('created_at')->limit(15)->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
