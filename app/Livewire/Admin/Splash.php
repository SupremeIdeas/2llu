<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SplashSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Admin → Settings → Appearance → Splash Screen (blueprint Section 24.2). Edits
 * the splash.* settings; saving busts the splash cache so the change reflects
 * immediately with no redeploy. Logos are Wasabi/CDN URLs (light + dark).
 */
#[Layout('components.layouts.admin')]
class Splash extends Component
{
    #[Validate('boolean')]
    public bool $enabled = false;

    #[Validate('string|max:60')]
    public string $product_name = '';

    #[Validate('string|max:60')]
    public string $brand_tagline = '';

    #[Validate('integer|min:0|max:4000')]
    public int $duration_ms = 1400;

    #[Validate('boolean')]
    public bool $show_once_per_session = true;

    #[Validate('nullable|url')]
    public string $product_logo_light = '';

    #[Validate('nullable|url')]
    public string $product_logo_dark = '';

    #[Validate('nullable|url')]
    public string $brand_logo_light = '';

    #[Validate('nullable|url')]
    public string $brand_logo_dark = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $s = SplashSettings::current();
        $this->enabled = $s['enabled'];
        $this->product_name = $s['product_name'];
        $this->brand_tagline = $s['brand_tagline'];
        $this->duration_ms = $s['duration_ms'];
        $this->show_once_per_session = $s['show_once_per_session'];
        $this->product_logo_light = $s['product_logo_light'];
        $this->product_logo_dark = $s['product_logo_dark'];
        $this->brand_logo_light = $s['brand_logo_light'];
        $this->brand_logo_dark = $s['brand_logo_dark'];
    }

    public function save(): void
    {
        $this->validate();

        foreach ([
            'splash.enabled' => $this->enabled,
            'splash.product_name' => $this->product_name,
            'splash.brand_tagline' => $this->brand_tagline,
            'splash.duration_ms' => $this->duration_ms,
            'splash.show_once_per_session' => $this->show_once_per_session,
            'splash.product_logo_light' => $this->product_logo_light,
            'splash.product_logo_dark' => $this->product_logo_dark,
            'splash.brand_logo_light' => $this->brand_logo_light,
            'splash.brand_logo_dark' => $this->brand_logo_dark,
        ] as $key => $value) {
            Setting::setValue($key, $value, 'appearance');
        }

        // Setting::saved busts the splash cache; belt-and-suspenders here too.
        SplashSettings::flush();
        Auditor::log('splash.updated', null, null, ['enabled' => $this->enabled]);

        $this->saved = 'Splash screen saved — it updates immediately, no redeploy.';
    }

    public function render()
    {
        return view('livewire.admin.splash');
    }
}
