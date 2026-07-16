<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\BrandSettings;
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

    // One upload slot per asset (all optional; blank = keep existing).
    public $product_light = null;

    public $product_dark = null;

    public $agency_light = null;

    public $agency_dark = null;

    public $favicon = null;

    public ?string $saved = null;

    /** field => setting key. */
    private const SLOTS = [
        'product_light' => 'brand.logo_product_light',
        'product_dark' => 'brand.logo_product_dark',
        'agency_light' => 'brand.logo_agency_light',
        'agency_dark' => 'brand.logo_agency_dark',
        'favicon' => 'brand.favicon',
    ];

    public function mount(): void
    {
        $this->brand_name = BrandSettings::name();
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
            'favicon' => 'nullable|image|max:1024',
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
        Auditor::log('brand.updated');
        $this->saved = 'Branding saved. Your logo and name now show across the platform.';
        $this->dispatch('nx-toast', type: 'success', message: 'Branding saved — live everywhere.');
    }

    public function render()
    {
        return view('livewire.admin.branding', [
            'brand' => BrandSettings::current(),
        ]);
    }
}
