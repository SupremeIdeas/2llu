<?php

namespace App\Livewire;

use App\Models\GiftCardProduct;
use App\Support\FeatureFlags;
use App\Support\GiftCardPricing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Naara Gift storefront (Phase 2 — browse + brand detail + pricing). Behind the
 * `naara_gift` feature flag (off until the catalogue is synced and Phase-3
 * checkout is live). Shows the unified Reloadly/Zendit catalogue as brands, with
 * FIXED/RANGE denominations priced through PricingEngine — provider + cost never
 * exposed. The purchase money path is Phase 3.
 */
#[Layout('components.layouts.customer')]
class GiftCards extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?string $country = null;

    public ?int $selectedId = null;

    /** Chosen amount (FIXED face or RANGE value). */
    public $amount = null;

    /** Dynamic required-field values, keyed by field key. */
    public array $fields = [];

    public function booted(): void
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $product = GiftCardProduct::storefront()->findOrFail($id);
        $this->selectedId = $product->id;
        $this->amount = null;
        $this->fields = collect($product->required_fields ?? [])
            ->mapWithKeys(fn ($f) => [($f['key'] ?? 'field') => ''])->all();
    }

    public function close(): void
    {
        $this->selectedId = null;
        $this->amount = null;
        $this->fields = [];
    }

    public function render()
    {
        $products = GiftCardProduct::storefront()
            ->when($this->search !== '', fn ($q) => $q->where('brand_name', 'like', '%'.$this->search.'%'))
            ->when($this->country, fn ($q) => $q->where('country', $this->country))
            ->orderByDesc('featured')->orderBy('brand_name')
            ->paginate(18);

        $selected = $this->selectedId ? GiftCardProduct::storefront()->find($this->selectedId) : null;
        $denominations = $selected ? app(GiftCardPricing::class)->denominations($selected) : null;

        return view('livewire.gift-cards', [
            'products' => $products,
            'countries' => GiftCardProduct::storefront()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'selected' => $selected,
            'denominations' => $denominations,
        ]);
    }
}
