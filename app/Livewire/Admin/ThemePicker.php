<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\Auditor;
use App\Support\MediaStorage;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * NAARA THEME SYSTEM — Batch 2 §4 (+ hero-image editing, owner request). Admin →
 * Theme picker. Select one of the 20 presets and apply it platform-wide, and —
 * per preset — upload/replace/remove the hero image shown on Dashboard, eSIM and
 * Numbers under that theme. No other inline token editing (colours/radius/type
 * stay seed-defined). Applying writes the slug to Setting, busts the theme cache,
 * and audits who/when — the next page load anywhere reflects it.
 *
 * Consistent with the other appearance admin screens (Dashboard theme, Branding):
 * same card grid, same confirm-before-commit seriousness this changes what every
 * logged-in user sees. Hero uploads follow Branding's own hero-art discipline
 * (WebP/JPG, capped small) so no theme ships a heavy image.
 */
#[Layout('components.layouts.admin')]
class ThemePicker extends Component
{
    use WithFileUploads;

    /** Entangled to <x-ui.modal> — its own close paths (X, backdrop, Escape)
     *  write a plain boolean here, so this is the only source of open/closed. */
    public bool $showHeroModal = false;

    /** Slug of the theme the modal is showing. Stays set after close (harmless
     *  — nothing reads it while $showHeroModal is false); editHero() always
     *  overwrites it before the modal opens. */
    public ?string $editingSlug = null;

    public string $editingName = '';

    /** Existing saved hero URL per surface for the theme being edited. */
    public array $currentHero = ['dashboard' => null, 'esim' => null, 'numbers' => null];

    // One optional upload per surface; blank = leave that surface untouched.
    public $hero_dashboard = null;

    public $hero_esim = null;

    public $hero_numbers = null;

    private const SURFACES = ['dashboard', 'esim', 'numbers'];

    public function mount(): void
    {
        $this->gate();
    }

    // Mirror EsimControlCenter's gate: role, or the delegable theme.manage
    // scope (registered in Batch 3 §6; role always suffices meanwhile).
    private function gate(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['super_admin', 'admin']) || Auth::user()?->can('theme.manage'),
            403,
        );
    }

    /** Open the hero-image editor for one theme, seeding it with what's saved. */
    public function editHero(string $slug): void
    {
        $this->gate();

        $row = ThemePresetModel::where('slug', $slug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');

            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        $this->editingSlug = $slug;
        $this->editingName = $row->name;
        $this->currentHero = [
            'dashboard' => $assets['dashboard'] ?? null,
            'esim' => $assets['esim'] ?? null,
            'numbers' => $assets['numbers'] ?? null,
        ];
        $this->hero_dashboard = null;
        $this->hero_esim = null;
        $this->hero_numbers = null;
        $this->resetErrorBag();
        $this->showHeroModal = true;
    }

    /**
     * Save whichever surface uploads were provided for the theme being edited.
     * A blank surface is left exactly as it was — this is a partial update, not
     * a full replace, so editing one surface never clears the others.
     */
    public function saveHero(): void
    {
        $this->gate();

        if ($this->editingSlug === null) {
            return;
        }

        $this->validate([
            'hero_dashboard' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_esim' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_numbers' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ], [
            'hero_dashboard.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_esim.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_numbers.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dashboard.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_esim.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_numbers.max' => 'Keep the hero image under 600 KB for fast loading.',
        ]);

        $row = ThemePresetModel::where('slug', $this->editingSlug)->first();
        if ($row === null) {
            $this->dispatch('nx-toast', type: 'error', message: 'Unknown theme.');
            $this->showHeroModal = false;

            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        $changed = false;
        foreach (self::SURFACES as $surface) {
            $field = 'hero_'.$surface;
            if ($this->{$field}) {
                $assets[$surface] = MediaStorage::storePublic($this->{$field}, 'theme-hero');
                $changed = true;
            }
        }

        if ($changed) {
            $row->hero_assets = $assets;
            $row->save();
            ThemePreset::bust(); // only matters if this is the active theme
            Auditor::log('theme.hero_updated', ThemePresetModel::class, $row->id, ['slug' => $this->editingSlug]);
        }

        $this->dispatch('nx-toast', type: 'success', message: $this->editingName.' hero images saved.');
        $this->showHeroModal = false;
    }

    /** Clear one surface's hero override for the theme being edited. */
    public function removeHeroSurface(string $surface): void
    {
        $this->gate();

        if ($this->editingSlug === null || ! in_array($surface, self::SURFACES, true)) {
            return;
        }

        $row = ThemePresetModel::where('slug', $this->editingSlug)->first();
        if ($row === null) {
            return;
        }

        $assets = is_array($row->hero_assets) ? $row->hero_assets : [];
        unset($assets[$surface]);
        $row->hero_assets = $assets;
        $row->save();

        $this->currentHero[$surface] = null;
        ThemePreset::bust();
        Auditor::log('theme.hero_removed', ThemePresetModel::class, $row->id, ['slug' => $this->editingSlug, 'surface' => $surface]);
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($surface).' hero image removed for '.$this->editingName.'.');
    }

    /**
     * Apply a preset platform-wide. Rejects an unknown slug server-side (never
     * trust the posted value), writes the active-theme Setting, busts the cache,
     * and logs the change.
     */
    public function apply(string $slug): void
    {
        $this->gate();

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
