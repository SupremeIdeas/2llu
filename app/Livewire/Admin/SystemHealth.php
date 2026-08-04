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
        ]);
    }
}
