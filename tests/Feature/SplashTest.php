<?php

namespace Tests\Feature;

use App\Livewire\Admin\Splash as SplashPanel;
use App\Models\Setting;
use App\Models\User;
use App\Support\SplashSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplashTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_defaults_are_off_with_the_supreme_ideas_tagline(): void
    {
        $s = SplashSettings::current();

        $this->assertFalse($s['enabled']);
        $this->assertSame('from Supreme Ideas', $s['brand_tagline']);
        $this->assertSame(1400, $s['duration_ms']);
    }

    public function test_settings_change_reflects_immediately_via_cache_bust(): void
    {
        SplashSettings::current(); // prime cache (disabled)

        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_name', 'NaaraSim');
        Setting::setValue('splash.brand_tagline', 'from Supreme Ideas Agency');

        // No manual flush — the Setting::saved hook cleared the cache.
        $s = SplashSettings::current();
        $this->assertTrue($s['enabled']);
        $this->assertSame('from Supreme Ideas Agency', $s['brand_tagline']);
    }

    public function test_duration_is_capped_so_it_never_feels_slow(): void
    {
        Setting::setValue('splash.duration_ms', 99999);
        $this->assertSame(4000, SplashSettings::current()['duration_ms']);
    }

    public function test_splash_renders_theme_aware_overlay_only_when_enabled(): void
    {
        // Disabled -> nothing.
        $this->blade('<x-splash />')->assertDontSee('z-[9999]', false);

        Setting::setValue('splash.enabled', true);
        Setting::setValue('splash.product_name', 'NaaraSim');

        $this->blade('<x-splash />')
            ->assertSee('NaaraSim')
            ->assertSee('from Supreme Ideas', false)
            ->assertSee('z-[9999]', false)
            ->assertSee('dark:bg-navy', false); // correct-theme paint (no flash)
    }

    public function test_admin_can_toggle_and_rebrand_the_splash_without_redeploy(): void
    {
        Livewire::actingAs($this->admin())->test(SplashPanel::class)
            ->set('enabled', true)
            ->set('product_name', 'NaaraSim')
            ->set('brand_tagline', 'from Supreme Ideas')
            ->set('product_logo_light', 'https://cdn.naarasim.com/logo-light.svg')
            ->set('duration_ms', 1200)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', 'Splash screen saved — it updates immediately, no redeploy.');

        $s = SplashSettings::current();
        $this->assertTrue($s['enabled']);
        $this->assertSame('https://cdn.naarasim.com/logo-light.svg', $s['product_logo_light']);
        $this->assertSame(1200, $s['duration_ms']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'splash.updated']);
    }

    public function test_invalid_logo_url_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(SplashPanel::class)
            ->set('product_logo_light', 'not-a-url')
            ->call('save')
            ->assertHasErrors('product_logo_light');
    }
}
