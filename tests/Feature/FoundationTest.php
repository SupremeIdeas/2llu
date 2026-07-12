<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_the_blank_layout_boots_with_the_theme_toggle(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // No-flash pre-paint theme script (Section 4.2 / 24.3).
        $response->assertSee('prefers-color-scheme', false);
        // Class-based dark toggle on a blank layout.
        $response->assertSee('Toggle dark mode', false);
        $response->assertSee('classList.toggle', false);
    }

    public function test_horizon_is_admin_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'), 'guest must be denied');
        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'), 'plain user must be denied');
        $this->assertFalse(Gate::forUser($staff)->allows('viewHorizon'), 'staff must be denied');
        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'), 'admin must be allowed');
        $this->assertTrue(Gate::forUser($super)->allows('viewHorizon'), 'super_admin must be allowed');
    }

    public function test_super_admin_bypasses_all_gates(): void
    {
        Gate::define('some-locked-ability', fn () => false);

        $super = User::factory()->create();
        $super->assignRole('super_admin');

        $this->assertTrue(Gate::forUser($super)->allows('some-locked-ability'));
    }

    public function test_wasabi_disk_is_configured_and_default(): void
    {
        $this->assertSame('wasabi', config('filesystems.default'));
        $this->assertSame('s3', config('filesystems.disks.wasabi.driver'));
        $this->assertSame('private', config('filesystems.disks.wasabi.visibility'));
    }
}
