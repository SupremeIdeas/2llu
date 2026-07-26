<?php

namespace App\Livewire;

use App\Models\EsimCompatibleDevice;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * eSIM device compatibility modal (esim_upgrade Part 2). Search across all
 * devices, Apple / Android / Others pill tabs, and per-category accordions —
 * all backed by the normalised esim_compatible_devices table. Includes the
 * *#06# / EID guidance so a user can confirm eSIM support before buying.
 *
 * Opened from anywhere via a dispatched `open-compatibility` event (one modal,
 * reused), matching the app's existing single-modal pattern.
 */
class EsimCompatibility extends Component
{
    public bool $open = false;

    public string $os = 'apple';

    public string $search = '';

    #[On('open-compatibility')]
    public function openModal(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->search = '';
    }

    public function setOs(string $os): void
    {
        if (in_array($os, ['apple', 'android', 'others'], true)) {
            $this->os = $os;
        }
    }

    public function render()
    {
        // Group the active tab's devices by category → brand, honouring search.
        $devices = EsimCompatibleDevice::query()
            ->where('os_group', $this->os)
            ->when($this->search !== '', fn ($q) => $q->where('device_name', 'like', '%'.$this->search.'%'))
            ->orderBy('category')->orderBy('brand')->orderBy('device_name')
            ->get()
            ->groupBy('category');

        return view('livewire.esim-compatibility', [
            'grouped' => $devices,
            'categoryLabels' => [
                'phone' => 'Phones', 'tablet' => 'Tablets', 'watch' => 'Smart Watches', 'laptop' => 'Laptops',
            ],
        ]);
    }
}
