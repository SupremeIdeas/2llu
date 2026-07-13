<?php

namespace App\Livewire\Admin;

use App\Models\EsimOrder;
use App\Models\OrderLog;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin overview (blueprint Sections 13.3 & 17). Provider health + wallet
 * balances (from providers:health-check), Active/Coming-Soon per product, and
 * a 30-day profit snapshot (revenue, cost, gross profit) — the cost figures
 * are admin-only, behind the admin gate.
 */
#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        $revenue = (float) EsimOrder::where('created_at', '>=', now()->subDays(30))->sum('price_charged');
        $cost = (float) OrderLog::where('created_at', '>=', now()->subDays(30))->sum('provider_cost');

        return view('livewire.admin.dashboard', [
            'health' => Cache::get('providers:health', []),
            'statuses' => ProviderStatus::all(),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => round($revenue - $cost, 2),
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0.0,
        ]);
    }
}
