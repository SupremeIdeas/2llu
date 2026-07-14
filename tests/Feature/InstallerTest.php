<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use App\Support\ProviderStatus;
use Database\Seeders\DefaultAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Installer::unlock();
        Installer::$envPath = storage_path('framework/testing/.env.install-test');
        @unlink(Installer::$envPath);
    }

    protected function tearDown(): void
    {
        @unlink(Installer::$envPath);
        Installer::$envPath = null;
        Installer::markInstalled();
        parent::tearDown();
    }

    public function test_a_fresh_server_redirects_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_welcome_then_requirements_are_reachable(): void
    {
        $this->get('/install')->assertOk()->assertSee('Let’s start', false);
        $this->get('/install/requirements')->assertOk()->assertSee('Server Requirements');
        $this->get('/install/setup')->assertOk()->assertSee('Setup')->assertSee('Environment');
    }

    public function test_installed_app_closes_the_installer(): void
    {
        Installer::markInstalled();
        $this->get('/install')->assertRedirect('/login');
    }

    public function test_install_creates_the_default_super_admin_and_shows_the_done_screen(): void
    {
        $response = $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'shared',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ]);

        $response->assertOk()
            ->assertSee('Installation Completed')
            ->assertSee(DefaultAdminSeeder::EMAIL, false)
            ->assertSee(DefaultAdminSeeder::PASSWORD, false);

        $admin = User::where('email', DefaultAdminSeeder::EMAIL)->firstOrFail();
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue(Installer::isInstalled());

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('APP_NAME=NaaraSim', $env);
        $this->assertStringContainsString('APP_URL=https://naarasim.test', $env);
    }

    public function test_shared_hosting_writes_database_drivers(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'shared',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertOk();

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('CACHE_STORE=database', $env);
        $this->assertStringContainsString('SESSION_DRIVER=database', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $env);
    }

    public function test_vps_hosting_writes_redis_drivers(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'hosting_type' => 'vps',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertOk();

        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('CACHE_STORE=redis', $env);
        $this->assertStringContainsString('SESSION_DRIVER=redis', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=redis', $env);
    }

    public function test_hosting_type_is_required(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test',
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertSessionHasErrors('hosting_type');

        $this->assertFalse(Installer::isInstalled());
    }

    public function test_app_url_with_a_trailing_slash_is_rejected(): void
    {
        $this->post('/install/setup', [
            'app_name' => 'NaaraSim',
            'app_url' => 'https://naarasim.test/', // trailing slash
            'db_connection' => 'sqlite',
            'db_database' => 'naarasim',
        ])->assertSessionHasErrors('app_url');

        $this->assertFalse(Installer::isInstalled());
    }

    public function test_coming_soon_when_a_provider_key_is_blank(): void
    {
        config(['services.getatext.api_key' => 'sk_live_123', 'services.fivesim.api_key' => null]);

        $this->assertSame('Active', ProviderStatus::label('getatext'));
        $this->assertSame('Coming Soon', ProviderStatus::label('fivesim'));
    }
}
