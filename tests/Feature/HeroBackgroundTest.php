<?php

namespace Tests\Feature;

use App\Livewire\Admin\Branding;
use App\Livewire\Dashboard;
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
        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertDontSee('object-cover object-center', false);

        Setting::setValue(HeroBackground::LIGHT_KEY, 'https://cdn/aurora.webp', 'brand');
        HeroBackground::flush();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('https://cdn/aurora.webp', false);
    }

    public function test_admin_can_toggle_the_dashboard_hero_image_off_without_deleting_it(): void
    {
        $user = User::factory()->create();
        Setting::setValue(HeroBackground::LIGHT_KEY, 'https://cdn/aurora.webp', 'brand');
        HeroBackground::flush();

        // On by default → image shows.
        $this->assertTrue(HeroBackground::showsOnDashboard());
        Livewire::actingAs($user)->test(Dashboard::class)->assertSee('https://cdn/aurora.webp', false);

        // Admin flips it off via Branding — the image is hidden but not removed.
        Livewire::actingAs($this->admin())->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('hero_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(HeroBackground::enabled());
        $this->assertFalse(HeroBackground::showsOnDashboard());
        $this->assertTrue(HeroBackground::isSet()); // still uploaded
        Livewire::actingAs($user)->test(Dashboard::class)->assertDontSee('https://cdn/aurora.webp', false);
    }

    public function test_the_dashboard_shows_the_description_default_then_the_admin_override(): void
    {
        $user = User::factory()->create();

        // Never empty — the sensible default shows out of the box (BUILD-13 §3.1).
        $this->assertSame(HeroBackground::DEFAULT_DESCRIPTION, HeroBackground::description());
        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee(HeroBackground::DEFAULT_DESCRIPTION);

        Setting::setValue(HeroBackground::DESC_KEY, 'Data and numbers, anywhere.', 'brand');
        HeroBackground::flush();

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSee('Data and numbers, anywhere.')
            ->assertDontSee(HeroBackground::DEFAULT_DESCRIPTION);
    }

    public function test_admin_can_edit_the_dashboard_description(): void
    {
        Storage::fake('public');
        Livewire::actingAs($this->admin())->test(Branding::class)
            ->set('brand_name', 'NaaraSim')
            ->set('hero_description', '  Stay connected, no swaps.  ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Stay connected, no swaps.', HeroBackground::description());
    }

    public function test_the_hero_block_degrades_cleanly_with_no_image(): void
    {
        // No hero image set: title, description and BOTH CTAs still render, in the
        // strict two-column grid that never collapses (BUILD-13 §1.4, §3.4).
        $this->assertFalse(HeroBackground::isSet());

        Livewire::actingAs(User::factory()->create())->test(Dashboard::class)
            ->assertSee('Connectivity')   // headline ("My" / "Connectivity" split across lines)
            ->assertSee(HeroBackground::DEFAULT_DESCRIPTION)
            ->assertSee('Buy eSIM')
            ->assertSee('Get Number')
            ->assertSee('mt-6 flex items-center gap-2.5', false); // pills stay side-by-side at every width
    }
}
