<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\BrandSettings;
use App\Support\HeroBackground;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin → Branding (Module 26). Upload the logo set (PNG/JPG/WebP/SVG) and set
 * the brand name; everything re-brands with no redeploy. Each logo is stored on
 * the public disk via MediaStorage and its URL saved as a setting. The display
 * side (<x-brand-logo>) scales each logo responsibly (max-height + object-contain)
 * so any reasonable PNG looks right without pre-processing.
 */
#[Layout('components.layouts.admin')]
class Branding extends Component
{
    use WithFileUploads;

    public string $brand_name = '';

    // Brand theme (Module 26) — hex colours + control roundness + preloader.
    public string $color_primary = '';

    public string $color_accent = '';

    public string $color_navy = '';

    public string $color_action = '';

    public string $radius = '0.5rem';

    public bool $preloader_enabled = false;

    public string $preloader_style = 'pulse-logo';

    // One upload slot per asset (all optional; blank = keep existing).
    public $product_light = null;

    public $product_dark = null;

    public $agency_light = null;

    public $agency_dark = null;

    // Naara Gift storefront logo (its own sub-brand mark) — light + dark.
    public $gift_light = null;

    public $gift_dark = null;

    public $favicon = null;

    // Premium dashboard hero backgrounds (owner request) — light + dark, WebP/JPG.
    public $hero_light = null;

    public $hero_dark = null;

    public ?string $saved = null;

    /** field => setting key. */
    private const SLOTS = [
        'product_light' => 'brand.logo_product_light',
        'product_dark' => 'brand.logo_product_dark',
        'agency_light' => 'brand.logo_agency_light',
        'agency_dark' => 'brand.logo_agency_dark',
        'gift_light' => 'brand.logo_gift_light',
        'gift_dark' => 'brand.logo_gift_dark',
        'favicon' => 'brand.favicon',
        'hero_light' => HeroBackground::LIGHT_KEY,
        'hero_dark' => HeroBackground::DARK_KEY,
    ];

    public function mount(): void
    {
        $this->brand_name = BrandSettings::name();
        $this->color_primary = BrandSettings::color('primary');
        $this->color_accent = BrandSettings::color('accent');
        $this->color_navy = BrandSettings::color('navy');
        $this->color_action = BrandSettings::color('action');
        $this->radius = BrandSettings::radius();
        $this->preloader_enabled = BrandSettings::preloaderEnabled();
        $this->preloader_style = BrandSettings::preloaderStyle();
    }

    /** Save the brand theme (colours, roundness, preloader). Takes effect live. */
    public function saveTheme(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'color_primary' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_accent' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_navy' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'color_action' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'radius' => 'required|in:0rem,0.25rem,0.5rem,0.75rem,1rem',
            'preloader_style' => 'required|in:'.implode(',', BrandSettings::PRELOADER_STYLES),
        ], [
            'color_primary.regex' => 'Use a 6-digit hex colour like #0A6E6E.',
            'color_accent.regex' => 'Use a 6-digit hex colour like #D4A017.',
            'color_navy.regex' => 'Use a 6-digit hex colour like #0D1B2A.',
            'color_action.regex' => 'Use a 6-digit hex colour like #E8412A.',
        ]);

        Setting::setValue('brand.color_primary', $this->color_primary, 'brand');
        Setting::setValue('brand.color_accent', $this->color_accent, 'brand');
        Setting::setValue('brand.color_navy', $this->color_navy, 'brand');
        Setting::setValue('brand.color_action', $this->color_action, 'brand');
        Setting::setValue('brand.radius', $this->radius, 'brand');
        Setting::setValue('brand.preloader_enabled', $this->preloader_enabled, 'brand');
        Setting::setValue('brand.preloader_style', $this->preloader_style, 'brand');

        BrandSettings::flush();
        Auditor::log('brand.theme_updated');
        $this->saved = 'Brand theme saved — the new colours are live across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Brand theme saved.');
    }

    /** Reset colours + roundness to the shipped brand defaults. */
    public function resetTheme(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach (['brand.color_primary', 'brand.color_accent', 'brand.color_navy', 'brand.color_action', 'brand.radius'] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        BrandSettings::flush();
        $this->color_primary = BrandSettings::color('primary');
        $this->color_accent = BrandSettings::color('accent');
        $this->color_navy = BrandSettings::color('navy');
        $this->color_action = BrandSettings::color('action');
        $this->radius = BrandSettings::radius();
        Auditor::log('brand.theme_reset');
        $this->saved = 'Brand colours reset to the NaaraSim defaults.';
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'brand_name' => 'required|string|max:60',
            'product_light' => 'nullable|image|max:2048',
            'product_dark' => 'nullable|image|max:2048',
            'agency_light' => 'nullable|image|max:2048',
            'agency_dark' => 'nullable|image|max:2048',
            'gift_light' => 'nullable|image|max:2048',
            'gift_dark' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            // Hero art: WebP or JPG only, kept small for fast in-app loading.
            'hero_light' => 'nullable|mimes:webp,jpg,jpeg|max:600',
            'hero_dark' => 'nullable|mimes:webp,jpg,jpeg|max:600',
        ], [
            'hero_light.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_dark.mimes' => 'The hero image must be a WebP or JPG.',
            'hero_light.max' => 'Keep the hero image under 600 KB for fast loading.',
            'hero_dark.max' => 'Keep the hero image under 600 KB for fast loading.',
        ]);

        Setting::setValue('brand.name', trim($this->brand_name), 'brand');

        foreach (self::SLOTS as $field => $key) {
            if ($this->{$field}) {
                $url = MediaStorage::storePublic($this->{$field}, 'brand');
                Setting::setValue($key, $url, 'brand');
                $this->{$field} = null;
            }
        }

        BrandSettings::flush();
        HeroBackground::flush();
        Auditor::log('brand.updated');
        $this->saved = 'Branding saved. Your logo and name now show across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Branding saved — live everywhere.');
    }

    /** Remove the hero backgrounds — the dashboard hero returns to its default. */
    public function removeHero(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        foreach ([HeroBackground::LIGHT_KEY, HeroBackground::DARK_KEY] as $key) {
            Setting::where('key', $key)->get()->each->delete();
        }
        HeroBackground::flush();
        Auditor::log('brand.hero_removed');
        $this->saved = 'Hero backgrounds removed — the dashboard uses the default heading.';
    }

    public function render()
    {
        return view('livewire.admin.branding', [
            'brand' => BrandSettings::current(),
        ]);
    }
}
