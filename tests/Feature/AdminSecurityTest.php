<?php

namespace Tests\Feature;

use App\Livewire\Admin\Security;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Module 14 — Secure Admin Route (blueprint Section 25).
 */
class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(bool $with2fa = true): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        if ($with2fa) {
            $user->forceFill([
                'two_factor_secret' => encrypt('SECRETKEY'),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    public function test_the_admin_path_is_env_driven(): void
    {
        // config/admin.php reads ADMIN_PATH from the environment…
        putenv('ADMIN_PATH=vault-x9');
        $fresh = require base_path('config/admin.php');
        putenv('ADMIN_PATH'); // restore
        $this->assertSame('vault-x9', $fresh['path']);

        // …and the admin route group is mounted on config('admin.path').
        $this->assertStringEndsWith('/'.config('admin.path'), route('admin.dashboard'));
    }

    public function test_guests_non_admins_and_the_guessable_admin_path_all_404(): void
    {
        // Guest at the real path — plain 404, never a login redirect.
        $this->get('/adminmaster')->assertNotFound();

        // A common guess.
        $this->get('/admin')->assertNotFound();

        // Authenticated non-admin.
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAs($user)->get('/adminmaster')->assertNotFound();
    }

    public function test_an_admin_without_2fa_is_forced_to_enrol_before_the_panel_opens(): void
    {
        $admin = $this->admin(with2fa: false);

        // Every admin page bounces to the security page…
        $this->actingAs($admin)->get('/adminmaster')->assertRedirect(route('admin.security'));
        $this->actingAs($admin)->get('/adminmaster/pricing')->assertRedirect(route('admin.security'));

        // …except the security page itself, which must be reachable to enrol.
        $this->actingAs($admin)->get('/adminmaster/security')->assertOk();
    }

    public function test_an_admin_with_confirmed_2fa_reaches_the_panel(): void
    {
        $this->actingAs($this->admin())->get('/adminmaster')->assertOk();
    }

    public function test_ip_allow_list_hides_the_panel_from_other_ips(): void
    {
        config(['admin.ip_allowlist' => ['10.0.0.5']]);

        $admin = $this->admin();

        // Default test IP (127.0.0.1) is not in the list -> 404 even for an admin.
        $this->actingAs($admin)->get('/adminmaster')->assertNotFound();

        // A request from an allowed IP gets in.
        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->get('/adminmaster')
            ->assertOk();
    }

    public function test_admin_can_enable_and_confirm_two_factor(): void
    {
        $admin = $this->admin(with2fa: false);

        $component = Livewire::actingAs($admin)->test(Security::class)
            ->assertSet('showingSetup', false)
            ->call('enable')
            ->assertSet('showingSetup', true);

        // A real TOTP for the freshly generated secret confirms enrolment.
        $secret = decrypt($admin->fresh()->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $component->set('code', $code)
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('showingSetup', false);

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa_confirmed']);
    }

    public function test_a_wrong_2fa_code_is_rejected(): void
    {
        $admin = $this->admin(with2fa: false);

        Livewire::actingAs($admin)->test(Security::class)
            ->call('enable')
            ->set('code', '000000')
            ->call('confirm')
            ->assertHasErrors('code');

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }
}
