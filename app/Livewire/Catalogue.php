<?php

namespace App\Livewire;

use App\Models\EsimPlan;
use App\Support\CountryNames;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    /** Country filter (ISO2), set via the shared CountryPicker. Deep-linkable. */
    #[Url(as: 'country')]
    public string $country = '';

    /** A coupon claimed from an announcement (?claim=CODE) — stashed for checkout. */
    #[Url(as: 'claim')]
    public string $claim = '';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['data', 'full'], true) ? $tab : 'data';
        // A country with data plans may have no Full eSIMs (and vice versa), so
        // clear the country filter when switching lines.
        $this->country = '';
        $this->resetPage();
    }

    /** Open the shared country picker, scoped to the active line. */
    public function browseCountries(): void
    {
        $this->dispatch('open-country-picker',
            source: 'esim',
            args: ['has_voice' => $this->tab === 'full'],
            for: 'catalogue',
            title: 'Browse eSIMs by country');
    }

    #[On('country-picked')]
    public function onCountryPicked(string $code, string $name, string $for): void
    {
        if ($for !== 'catalogue') {
            return; // another opener's pick — ignore
        }
        $this->country = strtoupper(trim($code));
        $this->resetPage();
    }

    public function clearCountry(): void
    {
        $this->country = '';
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

        $country = strtoupper(trim($this->country));

        $plans = EsimPlan::query()
            ->where('is_active', true)
            ->where('has_voice', $tab === 'full') // Data-only vs Full (calls + data)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($country !== '', fn ($q) => $q->whereJsonContains('countries', $country))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.catalogue', [
            'plans' => $plans,
            'tab' => $tab,
            'country' => $country,
            'countryName' => $country !== '' ? CountryNames::name($country) : '',
            'fullCount' => EsimPlan::where('is_active', true)->where('has_voice', true)->count(),
        ]);
    }
}
