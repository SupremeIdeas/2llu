<?php

namespace App\Livewire\Admin;

use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\Setting;
use App\Services\GiftCards\GiftCardCatalogueSyncService;
use App\Services\GiftCards\GiftCardOrderService;
use App\Support\Auditor;
use App\Support\GiftCardFraud;
use App\Support\ProviderStatus;
use App\Support\SyncStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin → Naara Gift. One screen where Reloadly and Zendit sit side by side as
 * "brothers and sisters": both provider statuses + last sync, the merged
 * catalogue (enable/disable/feature per brand), the fraud-control thresholds,
 * and the manual review queue (approve → fulfil, reject → refund). Reloadly is
 * primary; Zendit fills the gaps — the storefront only ever sells the primary.
 */
#[Layout('components.layouts.admin')]
class GiftCards extends Component
{
    use WithPagination;

    /** Fraud thresholds (admin-editable — no hardcoded limits). */
    public float $max_amount_24h = 500;

    public int $max_count_24h = 6;

    public float $max_amount_7d = 2000;

    public int $cooloff_hours = 24;

    public float $cooloff_value = 100;

    public float $review_threshold = 250;

    /** Catalogue browser filters. */
    public string $search = '';

    public string $providerFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach (GiftCardFraud::settings() as $k => $v) {
            $this->{$k} = $v;
        }
    }

    public function updated($name): void
    {
        if (in_array($name, ['search', 'providerFilter'], true)) {
            $this->resetPage('cat');
        }
    }

    public function saveFraud(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate([
            'max_amount_24h' => 'required|numeric|min:0',
            'max_count_24h' => 'required|integer|min:1',
            'max_amount_7d' => 'required|numeric|min:0',
            'cooloff_hours' => 'required|integer|min:0',
            'cooloff_value' => 'required|numeric|min:0',
            'review_threshold' => 'required|numeric|min:0',
        ]);

        foreach (['max_amount_24h', 'max_count_24h', 'max_amount_7d', 'cooloff_hours', 'cooloff_value', 'review_threshold'] as $k) {
            Setting::setValue('giftcards.'.$k, $this->{$k}, 'giftcards');
        }
        Auditor::log('giftcard.fraud_updated', null, null, ['review_threshold' => $this->review_threshold]);
        $this->dispatch('nx-toast', type: 'success', message: 'Fraud controls saved.');
    }

    /** Toggle a per-brand admin switch (enable / feature). */
    public function toggle(int $id, string $field): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($field, ['admin_enabled', 'featured'], true), 422);

        $product = GiftCardProduct::findOrFail($id);
        $product->update([$field => ! $product->{$field}]);
        $this->dispatch('nx-toast', type: 'success', message: 'Updated.');
    }

    /** Sync one provider's catalogue now. */
    public function sync(string $provider, GiftCardCatalogueSyncService $svc): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(in_array($provider, ['reloadly', 'zendit'], true), 422);

        $count = $svc->sync($provider);
        SyncStatus::flush();
        $this->dispatch('nx-toast', type: $count > 0 ? 'success' : 'error',
            message: $count > 0 ? ucfirst($provider).": synced {$count} products." : ucfirst($provider).': sync returned nothing (check keys).');
    }

    public function approve(int $id, GiftCardOrderService $orders): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        try {
            $orders->approveReview(GiftCardOrder::findOrFail($id));
            $this->dispatch('nx-toast', type: 'success', message: 'Order approved and delivered.');
        } catch (\Throwable $e) {
            $this->dispatch('nx-toast', type: 'error', message: 'Could not fulfil — the wallet was refunded.');
        }
    }

    public function reject(int $id, GiftCardOrderService $orders): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $orders->rejectReview(GiftCardOrder::findOrFail($id), 'Declined in manual review.');
        $this->dispatch('nx-toast', type: 'success', message: 'Order declined — buyer refunded.');
    }

    public function render()
    {
        // Both providers, side by side — status + last sync + catalogue depth.
        $providers = collect(['reloadly', 'zendit'])->map(fn ($p) => [
            'key' => $p,
            'label' => $p === 'reloadly' ? 'Reloadly' : 'Zendit',
            'role' => $p === 'reloadly' ? 'Primary' : 'Failover',
            'configured' => ProviderStatus::isActive($p),
            'sync' => SyncStatus::for('giftcards:'.$p),
            'total' => GiftCardProduct::where('provider', $p)->count(),
            'live' => GiftCardProduct::where('provider', $p)->where('is_primary', true)->where('admin_enabled', true)->count(),
        ])->all();

        $catalogue = GiftCardProduct::query()
            ->when($this->search !== '', fn ($q) => $q->where('brand_name', 'like', '%'.$this->search.'%'))
            ->when($this->providerFilter !== '', fn ($q) => $q->where('provider', $this->providerFilter))
            ->orderByDesc('featured')->orderByDesc('is_primary')->orderBy('brand_name')
            ->paginate(15, pageName: 'cat');

        $review = GiftCardOrder::with('user:id,name,email')
            ->where('status', GiftCardOrder::STATUS_REVIEW)->latest()->get();

        $recent = GiftCardOrder::with('user:id,name,email')
            ->whereIn('status', [GiftCardOrder::STATUS_DELIVERED, GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED])
            ->latest()->limit(20)->get();

        return view('livewire.admin.gift-cards', compact('providers', 'catalogue', 'review', 'recent'));
    }
}
