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

/**
 * Module 26 — Brand & design system (logos + fonts).
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        BrandSettings::flush();
    }

    public function test_defaults_to_the_app_name_and_no_logos(): void
    {
        $this->assertSame(config('app.name'), BrandSettings::name());
        $this->assertFalse(BrandSettings::hasProductLogo());
        $this->assertNull(BrandSettings::logo('product', 'light'));
    }

    public function test_a_saved_logo_url_is_returned_and_theme_variants_fall_back(): void
    {
        Setting::setValue('brand.logo_product_light', 'https://cdn.test/logo-light.png', 'brand');
        BrandSettings::flush();

        $this->assertTrue(BrandSettings::hasProductLogo());
        $this->assertSame('https://cdn.test/logo-light.png', BrandSettings::logo('product', 'light'));
        // logo() returns the exact variant (null if unset); the <x-brand-logo>
        // component does the light<->dark cross-fallback so a logo always shows.
        $this->assertNull(BrandSettings::logo('product', 'dark'));

        $html = view('components.brand-logo', ['variant' => 'product', 'class' => 'h-8'])->render();
        $this->assertStringContainsString('logo-light.png', $html); // used for the dark <img> too
    }

    public function test_brand_logo_component_falls_back_to_the_wordmark(): void
    {
        $html = view('components.brand-logo', ['variant' => 'product', 'class' => 'h-8', 'fallbackIcon' => 'signal'])->render();
        $this->assertStringContainsString(config('app.name'), $html); // wordmark text present
    }

    public function test_branding_page_is_admin_only_and_saves_the_brand_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster/branding')->assertNotFound();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim Global')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('NaaraSim Global', BrandSettings::name());
    }

    public function test_the_brand_fonts_are_present_and_wired(): void
    {
        $this->assertFileExists(public_path('fonts/supreme-display.ttf'));
        $this->assertFileExists(public_path('fonts/didact-gothic.woff2'));
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('Supreme Display', $css);
        $this->assertStringContainsString('Didact Gothic', $css);
    }
}
