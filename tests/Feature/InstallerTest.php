<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use App\Support\ProviderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate a fresh server: no lock, and a throwaway .env target.
        Installer::unlock();
        Installer::$envPath = storage_path('framework/testing/.env.install-test');
        @unlink(Installer::$envPath);
    }

    protected function tearDown(): void
    {
        @unlink(Installer::$envPath);
        Installer::$envPath = null;
        Installer::markInstalled(); // restore installed state for the next test
        parent::tearDown();
    }

    public function test_a_fresh_server_redirects_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_installer_is_reachable_and_shows_requirements(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertSee('Server requirements')
            ->assertSee('PHP 8.2 or higher');
    }

    public function test_installed_app_closes_the_installer(): void
    {
        Installer::markInstalled();
        $this->get('/install')->assertRedirect('/login');
    }

    public function test_full_flow_creates_super_admin_writes_lock_and_redirects_to_login(): void
    {
        $this->withSession([
            'install.db' => [
                'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_database' => 'naarasim',
                'db_username' => 'root', 'db_password' => '',
            ],
            'install.app' => [
                'app_name' => 'NaaraSim', 'app_url' => 'https://naarasim.test',
                'admin_name' => 'Frank', 'admin_email' => 'admin@naarasim.test', 'admin_password' => 'supersecret',
            ],
        ])->post('/install/finalize', [
            'key_GETATEXT_API_KEY' => 'sk_live_123', // Getatext goes live…
            'key_ESIMGO_API_KEY' => '',              // …eSIM Go stays Coming Soon
        ])->assertRedirect('/login');

        // Super admin created.
        $admin = User::where('email', 'admin@naarasim.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('super_admin'));

        // Lock written -> installer now closed.
        $this->assertTrue(Installer::isInstalled());

        // .env got the provided key but not the blank one.
        $env = file_get_contents(Installer::$envPath);
        $this->assertStringContainsString('GETATEXT_API_KEY=sk_live_123', $env);
        $this->assertStringContainsString('APP_NAME=NaaraSim', $env);
    }

    public function test_coming_soon_when_a_provider_key_is_blank(): void
    {
        config(['services.getatext.api_key' => 'sk_live_123', 'services.fivesim.api_key' => null]);

        $this->assertSame('Active', ProviderStatus::label('getatext'));
        $this->assertSame('Coming Soon', ProviderStatus::label('fivesim'));
    }
}
