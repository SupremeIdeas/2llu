<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Models\Setting;
use App\Models\User;
use App\Support\HeroBackground;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Premium dashboard hero backgrounds (owner request). An admin uploads a light
 * and a dark image (WebP/JPG); the dashboard hero renders them under a gradient,
 * theme-switched. With nothing uploaded the default heading is untouched.
 */
class HeroBackgroundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('admin');

        return $u;
    }

    public function test_admin_uploads_light_and_dark_hero_art(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.wasabi.key' => null]);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('hero_light', UploadedFile::fake()->image('aurora-light.jpg', 1600, 500))
            ->set('hero_dark', UploadedFile::fake()->image('aurora-dark.jpg', 1600, 500))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(HeroBackground::isSet());
        $this->assertNotNull(HeroBackground::light());
        $this->assertNotNull(HeroBackground::dark());
    }

    public function test_a_non_image_or_oversize_file_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('hero_light', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('hero_light');

        $this->assertFalse(HeroBackground::isSet());
    }

    public function test_removing_hero_returns_to_the_default(): void
    {
        Setting::setValue(HeroBackground::LIGHT_KEY, 'https://cdn/x.webp', 'brand');
        HeroBackground::flush();
        $this->assertTrue(HeroBackground::isSet());

        Livewire::actingAs($this->admin())->test(Branding::class)->call('removeHero');

        $this->assertFalse(HeroBackground::isSet());
    }

    public function test_the_dashboard_renders_the_hero_image_when_set_and_not_otherwise(): void
    {
        $user = User::factory()->create();

        // Default: no hero image element.
        Livewire::actingAs($user)->test(\App\Livewire\Dashboard::class)
            ->assertDontSee('object-cover object-center', false);

        Setting::setValue(HeroBackground::LIGHT_KEY, 'https://cdn/aurora.webp', 'brand');
        HeroBackground::flush();

        Livewire::actingAs($user)->test(\App\Livewire\Dashboard::class)
            ->assertSee('https://cdn/aurora.webp', false);
    }
}
