<?php

namespace Tests\Feature;

use App\Livewire\Admin\WelcomeSettings as AdminWelcomeSettings;
use App\Models\User;
use App\Support\WelcomeSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aurora Welcome Animation — the first-login entrance. Covers the signup→welcome
 * hand-off (only for genuine new users), the admin-only preview, the disabled
 * short-circuit, and the admin settings CRUD (save + reset).
 */
class WelcomeAuroraTest extends TestCase
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

    public function test_registration_routes_through_the_welcome_screen(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Ada', 'email' => 'ada@naara.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('welcome'));

        $this->assertTrue(session('just_registered'));
    }

    public function test_registration_skips_the_welcome_when_disabled(): void
    {
        Notification::fake();
        WelcomeSettings::save(['enabled' => false]);

        $this->post('/register', [
            'name' => 'Ben', 'email' => 'ben@naara.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertRedirect(config('fortify.home', '/dashboard'));
    }

    public function test_welcome_plays_for_a_just_registered_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->withSession(['just_registered' => true])
            ->get('/welcome')
            ->assertOk()
            ->assertSee('Welcome to');
    }

    public function test_welcome_redirects_a_normal_visit_to_the_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/welcome')->assertRedirect(route('dashboard'));
    }

    public function test_preview_is_admin_only(): void
    {
        // A normal user cannot force the animation with ?preview=1.
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/welcome?preview=1')
            ->assertRedirect(route('dashboard'));

        // An admin can preview it on demand.
        $this->actingAs($this->admin())
            ->get('/welcome?preview=1')
            ->assertOk()
            ->assertSee('Welcome to');
    }

    public function test_admin_settings_are_gated_and_persist(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminWelcomeSettings::class)->assertStatus(403);

        Livewire::actingAs($this->admin())->test(AdminWelcomeSettings::class)
            ->set('form.welcome_text', 'Kedu')
            ->set('form.tagline_text', 'Let us begin')
            ->set('form.logo_reveal_speed', 700)
            ->set('form.tagline_reveal_delay', 900)
            ->set('form.animation_total_duration', 3500)
            ->set('form.aurora_speed', 10)
            ->set('form.brand_color_1', '#0A6E6E')
            ->set('form.brand_color_2', '#E8412A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Kedu', WelcomeSettings::get('welcome_text'));
        $this->assertSame(10, WelcomeSettings::get('aurora_speed'));
    }

    public function test_admin_can_reset_to_defaults(): void
    {
        WelcomeSettings::save(['welcome_text' => 'Changed', 'aurora_speed' => 25]);

        Livewire::actingAs($this->admin())->test(AdminWelcomeSettings::class)->call('resetDefaults');

        $this->assertSame('Welcome to', WelcomeSettings::get('welcome_text'));
        $this->assertSame(8, WelcomeSettings::get('aurora_speed'));
    }

    public function test_invalid_timing_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(AdminWelcomeSettings::class)
            ->set('form.animation_total_duration', 100) // below the 1200ms floor
            ->call('save')
            ->assertHasErrors('form.animation_total_duration');
    }
}
