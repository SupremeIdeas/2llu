<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Module 26 — admin-editable brand colours, roundness, and preloader. */
class BrandThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        BrandSettings::flush();
    }

    public function test_hex_to_channels_and_darken_are_correct_and_safe(): void
    {
        $this->assertSame('10 110 110', BrandSettings::hexToChannels('#0A6E6E'));
        $this->assertSame('212 160 23', BrandSettings::hexToChannels('D4A017')); // no hash
        $this->assertSame('255 255 255', BrandSettings::hexToChannels('#fff'));   // shorthand
        $this->assertNull(BrandSettings::hexToChannels('not-a-colour'));
        $this->assertNull(BrandSettings::hexToChannels('#12'));
    }

    public function test_no_override_emits_no_theme_css(): void
    {
        $this->assertFalse(BrandSettings::hasThemeOverride());
        $this->assertSame('', BrandSettings::themeCss());
    }

    public function test_saving_colours_emits_a_root_override_with_channel_triples(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', '#112233')
            ->set('color_accent', '#D4A017')
            ->set('color_navy', '#0D1B2A')
            ->set('color_action', '#E8412A')
            ->set('radius', '1rem')
            ->set('preloader_enabled', true)
            ->call('saveTheme')
            ->assertHasNoErrors();

        BrandSettings::flush();
        $css = BrandSettings::themeCss();
        $this->assertStringContainsString('--brand-primary: 17 34 51;', $css);
        $this->assertStringContainsString('--brand-primary-dark:', $css); // auto-derived
        $this->assertStringContainsString('--brand-radius: 1rem;', $css);
        $this->assertTrue(BrandSettings::preloaderEnabled());

        // It actually renders into the page head.
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('--brand-primary: 17 34 51', false);
    }

    public function test_invalid_hex_is_rejected(): void
    {
        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('color_primary', 'teal')
            ->call('saveTheme')
            ->assertHasErrors(['color_primary']);
    }

    public function test_reset_returns_the_defaults(): void
    {
        Setting::setValue('brand.color_primary', '#112233', 'brand');
        BrandSettings::flush();
        $this->assertTrue(BrandSettings::hasThemeOverride());

        $admin = User::factory()->create()->fresh();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(Branding::class)->call('resetTheme');

        BrandSettings::flush();
        $this->assertFalse(BrandSettings::hasThemeOverride());
        $this->assertSame('#0A6E6E', BrandSettings::color('primary'));
    }

    public function test_preloader_renders_only_when_enabled(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('nx-preloader', false);

        Setting::setValue('brand.preloader_enabled', true, 'brand');
        BrandSettings::flush();
        $this->get('/login')->assertOk()->assertSee('nx-preloader', false);
    }

    public function test_radius_is_clamped_to_a_safe_value(): void
    {
        // A malformed radius can never reach the injected CSS.
        Setting::setValue('brand.color_primary', '#0A6E6E', 'brand');
        Setting::setValue('brand.radius', '9px;} body{display:none', 'brand');
        BrandSettings::flush();

        $css = BrandSettings::themeCss();
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('--brand-radius: 0.5rem;', $css);
    }
}
