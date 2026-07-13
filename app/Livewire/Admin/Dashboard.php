<?php

namespace App\Livewire\Admin;

use App\Models\EsimOrder;
use App\Models\OrderLog;
use App\Support\ProviderStatus;
use App\Support\StaffScopes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin overview (blueprint Sections 13.3, 17 & 27). super_admin/admin see the
 * provider health, product status, and a 30-day profit snapshot (cost figures
 * are admin-only). Staff see a scoped welcome — their granted scopes only, no
 * cost/profit — so the panel entry never leaks business figures to staff.
 */
#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return view('livewire.admin.dashboard', [
                'privileged' => false,
                'myScopes' => array_values(array_intersect(
                    StaffScopes::all(),
                    $user->getPermissionNames()->all()
                )),
                'scopeLabels' => StaffScopes::labels(),
            ]);
        }

        $revenue = (float) EsimOrder::where('created_at', '>=', now()->subDays(30))->sum('price_charged');
        $cost = (float) OrderLog::where('created_at', '>=', now()->subDays(30))->sum('provider_cost');

        return view('livewire.admin.dashboard', [
            'privileged' => true,
            'health' => Cache::get('providers:health', []),
            'statuses' => ProviderStatus::all(),
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => round($revenue - $cost, 2),
            'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : 0.0,
        ]);
    }
}
