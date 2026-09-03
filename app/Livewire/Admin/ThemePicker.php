<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * NAARA THEME SYSTEM — Batch 2 §4. Admin → Theme picker. Select one of the 15
 * presets and apply it platform-wide. This screen's ONLY job is select-and-apply
 * (no inline token editing in v1). Applying writes the slug to Setting, busts the
 * theme cache, and audits who/when — the next page load anywhere reflects it.
 *
 * Consistent with the other appearance admin screens (Dashboard theme, Branding):
 * same card grid, same confirm-before-commit seriousness this changes what every
 * logged-in user sees.
 */
#[Layout('components.layouts.admin')]
class ThemePicker extends Component
{
    public function mount(): void
    {
        // Mirror EsimControlCenter's gate: role, or the delegable theme.manage
        // scope (registered in Batch 3 §6; role always suffices meanwhile).
        abort_unless(
            Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('theme.manage'),
            403,
        );
    }

    /**
     * Apply a preset platform-wide. Rejects an unknown slug server-side (never
     * trust the posted value), writes the active-theme Setting, busts the cache,
     * and logs the change.
     */
    public function apply(string $slug): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('theme.manage'),
            403,
        );

        $exists = ThemePreset::all()->firstWhere('slug', $slug);
        if ($exists === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        Setting::setValue(ThemePreset::SETTING_KEY, $slug);
        ThemePreset::bust();
        Auditor::log('theme.applied', 'ThemePreset', null, ['slug' => $slug]);

        $this->dispatch('nx-toast', type: 'success', message: $exists['name'].' applied — it takes effect on the next page load.');
    }

    public function render()
    {
        return view('livewire.admin.theme-picker', [
            'presets' => ThemePreset::all(),
            'active' => ThemePreset::slug(),
        ]);
    }
}
