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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $plans = EsimPlan::query()
            ->where('is_active', true)
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.catalogue', ['plans' => $plans]);
    }
}
