<?php

namespace App\Livewire\Admin;

use App\Models\Merchant;
use App\Models\Setting;
use App\Services\Merchants\MerchantService;
use App\Support\Auditor;
use App\Support\MerchantSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Merchants (ROADMAP §Layer 3.6). Toggle the programme, set the global
 * reseller margin (MarginGuard still floors every resulting price), and
 * approve / reject / suspend merchants. The margin is admin-owned — merchants
 * never price their own products.
 */
#[Layout('components.layouts.admin')]
class Merchants extends Component
{
    public bool $enabled = false;

    public $resellerMargin = 10;

    public ?string $saved = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->enabled = MerchantSettings::enabled();
        $this->resellerMargin = MerchantSettings::resellerMarginPct();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['resellerMargin' => 'required|numeric|min:0|max:500']);

        Setting::setValue(MerchantSettings::FLAG, $this->enabled, 'merchants');
        Setting::setValue(MerchantSettings::MARGIN, (float) $this->resellerMargin, 'merchants');
        Auditor::log('merchants.settings_updated', null, null, ['enabled' => $this->enabled, 'margin' => $this->resellerMargin]);

        $this->saved = 'Merchant settings saved.';
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant settings saved.');
    }

    public function approve(int $id, MerchantService $merchants): void
    {
        $merchants->approve(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant approved.');
    }

    public function reject(int $id, MerchantService $merchants): void
    {
        $merchants->reject(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Application rejected.');
    }

    public function suspend(int $id, MerchantService $merchants): void
    {
        $merchants->suspend(Merchant::findOrFail($id), Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: 'Merchant suspended.');
    }

    public function render()
    {
        return view('livewire.admin.merchants', [
            'pending' => Merchant::with('owner:id,name,email')->where('status', Merchant::PENDING)->latest()->get(),
            'active' => Merchant::with('owner:id,name,email')->withCount('customers')
                ->whereIn('status', [Merchant::ACTIVE, Merchant::SUSPENDED])->latest()->get(),
        ]);
    }
}
