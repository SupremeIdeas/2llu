<?php

namespace App\Livewire;

use App\Models\EsimPlan;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Services\Merchants\MerchantClientService;
use App\Services\Merchants\MerchantException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Merchant V2 — client management. A V2 merchant manages clients who never log
 * in and subscribes eSIMs to them from the merchant's own wallet. Searchable,
 * paginated list (matching Admin\Users' pattern). 404s for a non-V2 merchant.
 */
#[Layout('components.layouts.customer')]
class MerchantClients extends Component
{
    use WithPagination;

    public string $search = '';

    // Add/edit form.
    public ?int $editingId = null;

    public string $name = '';

    public string $contact = '';

    public string $device = '';

    public string $notes = '';

    // Assign-eSIM buffer.
    public ?int $assignClientId = null;

    public ?int $assignPlanId = null;

    public ?string $error = null;

    private function merchant(): Merchant
    {
        $merchant = Auth::user()?->merchantAccount;
        abort_unless($merchant !== null && $merchant->isActive() && $merchant->isV2(), 404);

        return $merchant;
    }

    public function mount(): void
    {
        $this->merchant();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function save(MerchantClientService $service): void
    {
        $this->error = null;
        $this->validate([
            'name' => 'required|string|max:120',
            'contact' => 'nullable|string|max:120',
            'device' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:1000',
        ]);
        $merchant = $this->merchant();
        $data = ['name' => $this->name, 'contact' => $this->contact, 'device' => $this->device, 'notes' => $this->notes];

        try {
            if ($this->editingId) {
                $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($this->editingId);
                $service->updateClient($merchant, $client, $data);
            } else {
                $service->addClient($merchant, $data);
            }
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('editingId', 'name', 'contact', 'device', 'notes');
        $this->dispatch('nx-toast', type: 'success', message: 'Client saved.');
        $this->dispatch('close-client-sheet');
    }

    public function edit(int $id): void
    {
        $client = MerchantClient::where('merchant_id', $this->merchant()->id)->findOrFail($id);
        $this->editingId = $client->id;
        $this->name = $client->name;
        $this->contact = (string) $client->contact;
        $this->device = (string) $client->device;
        $this->notes = (string) $client->notes;
    }

    public function newClient(): void
    {
        $this->reset('editingId', 'name', 'contact', 'device', 'notes', 'error');
    }

    public function toggleActive(int $id, MerchantClientService $service): void
    {
        $merchant = $this->merchant();
        $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($id);
        $service->setActive($merchant, $client, ! $client->is_active);
    }

    public function assign(MerchantClientService $service): void
    {
        $this->error = null;
        $merchant = $this->merchant();
        $client = MerchantClient::where('merchant_id', $merchant->id)->findOrFail($this->assignClientId);
        $plan = EsimPlan::findOrFail($this->assignPlanId);

        try {
            $service->assignEsim($merchant, $client, $plan);
        } catch (MerchantException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset('assignClientId', 'assignPlanId');
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'eSIM assigned', message: "It's provisioning now and will show under the client.");
    }

    public function render()
    {
        $merchant = $this->merchant();
        $clients = MerchantClient::where('merchant_id', $merchant->id)
            ->withCount('esimOrders')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('device', 'like', $term)->orWhere('contact', 'like', $term));
            })
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(12);

        return view('livewire.merchant-clients', [
            'clients' => $clients,
            'plans' => EsimPlan::where('is_active', true)->orderBy('name')->limit(200)->get(['id', 'name']),
            'walletUsd' => round((float) ($merchant->owner->wallet?->usd_balance ?? 0), 2),
        ]);
    }
}
