<?php

namespace App\Livewire;

use App\Models\EsimPlan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * eSIM catalogue (blueprint Sections 4, 12, 14). Lists active plans with the
 * display price (USD + NGN via the accessor) — never cost. Search is debounced.
 */
#[Layout('components.layouts.customer')]
class Catalogue extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Storefront tab: 'data' (data-only) or 'full' (calls + data). Deep-linkable. */
    #[Url(as: 'tab')]
    public string $tab = 'data';

    /** A coupon claimed from an announcement (?claim=CODE) — stashed for checkout. */
    #[Url(as: 'claim')]
    public string $claim = '';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['data', 'full'], true) ? $tab : 'data';
        $this->resetPage();
    }

    public function mount(): void
    {
        if ($this->claim !== '') {
            \App\Support\PendingCoupon::stash($this->claim);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tab = in_array($this->tab, ['data', 'full'], true) ? $this->tab : 'data';

        $plans = EsimPlan::query()
            ->where('is_active', true)
            ->where('has_voice', $tab === 'full') // Data-only vs Full (calls + data)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.catalogue', [
            'plans' => $plans,
            'tab' => $tab,
            'fullCount' => EsimPlan::where('is_active', true)->where('has_voice', true)->count(),
        ]);
    }
}
