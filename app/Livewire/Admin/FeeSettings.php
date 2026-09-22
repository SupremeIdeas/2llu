<?php

namespace App\Livewire\Admin;

use App\Models\FeeSetting;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Fee Settings (2LLU Batch 1 §5d). Editable table for the four
 * globally-configurable fee types later batches' FeeCalculator reads —
 * every save is bounds-checked against the setting's own min_value/max_value
 * by FeeSetting::setValue(), never clamped silently.
 */
#[Layout('components.layouts.admin')]
class FeeSettings extends Component
{
    /** @var array<string, string> key => pending edited value, keyed for wire:model binding */
    public array $values = [];

    public ?string $saved = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->values = FeeSetting::query()->pluck('value', 'key')
            ->map(fn ($v) => (string) $v)->all();
    }

    public function save(string $key): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->error = null;
        $this->saved = null;

        try {
            FeeSetting::setValue($key, (float) $this->values[$key]);
            Auditor::log('fee_setting.updated', FeeSetting::class, null, ['key' => $key, 'value' => $this->values[$key]]);
            $this->saved = "Saved {$key}.";
            $this->dispatch('nx-toast', type: 'success', message: "Saved {$key}.");
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('nx-toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.fee-settings', [
            'settings' => FeeSetting::query()->orderBy('key')->get(),
        ]);
    }
}
