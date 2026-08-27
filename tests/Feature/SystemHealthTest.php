<?php

namespace Tests\Feature;

use App\Livewire\Admin\SystemHealth;
use App\Models\User;
use App\Support\SchedulerHealth;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HOTFIX §2 — the System Health diagnostic. Each scheduled task records its own
 * last run; the panel flags a task as overdue (cron not firing) and surfaces the
 * queue connection so "payment didn't credit / health widget empty" becomes a
 * visible cron/queue problem instead of a re-debug of correct code.
 */
class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_it_extracts_the_command_name_from_a_scheduler_string(): void
    {
        $this->assertSame('providers:health-check',
            SchedulerHealth::commandName("'/usr/bin/php' 'artisan' providers:health-check"));
        $this->assertSame('queue:work',
            SchedulerHealth::commandName('php artisan queue:work --stop-when-empty --tries=1'));
        $this->assertSame('esim:sync', SchedulerHealth::commandName('esim:sync'));
    }

    public function test_a_recorded_task_is_not_overdue_but_an_unrun_task_is(): void
    {
        SchedulerHealth::record("'/usr/bin/php' 'artisan' providers:health-check");

        $report = collect(SchedulerHealth::report())->keyBy('name');

        $this->assertFalse($report['providers:health-check']['overdue']); // just ran
        $this->assertNotNull($report['providers:health-check']['last_run']);
        $this->assertTrue($report['esim:sync']['overdue']); // never run this test
        $this->assertTrue(SchedulerHealth::anyOverdue());
    }

    public function test_the_page_is_admin_only_and_renders(): void
    {
        Livewire::actingAs(User::factory()->create())->test(SystemHealth::class)->assertStatus(403);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Provider health check');
    }

    public function test_the_page_shows_the_worker_layer_and_cache_controls(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Worker driver')
            ->assertSee('Horizon')
            ->assertSee('Failed jobs')
            ->assertSee('Clear app cache');
    }

    public function test_an_admin_can_flush_the_cache_and_a_user_cannot(): void
    {
        \Illuminate\Support\Facades\Cache::put('probe', 'x', 60);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->call('flushCache', 'app')
            ->assertDispatched('nx-toast');

        // A non-admin is refused at mount already (page is admin-only).
        Livewire::actingAs(User::factory()->create())->test(SystemHealth::class)->assertStatus(403);
    }

    public function test_the_worker_verdict_flags_a_sync_queue_as_degraded(): void
    {
        // The test env runs QUEUE_CONNECTION=sync, so jobs are not backgrounded.
        $verdict = \App\Support\QueueHealth::workerVerdict();
        $this->assertFalse($verdict['healthy']);
        $this->assertStringContainsString('inline', strtolower((string) $verdict['reason']));
    }

    public function test_the_hosting_guide_gives_ordered_steps_for_both_modes(): void
    {
        $steps = \App\Support\HostingGuide::steps();
        $this->assertArrayHasKey('shared', $steps);
        $this->assertArrayHasKey('vps', $steps);
        // The cron line carries the real app path + a schedule:run.
        $cron = \App\Support\HostingGuide::cronLine();
        $this->assertStringContainsString('artisan schedule:run', $cron);
        $this->assertStringContainsString(base_path(), $cron);
        // Shared drives the queue from cron; VPS runs Horizon.
        $this->assertStringContainsString('database', json_encode($steps['shared']));
        $this->assertStringContainsString('horizon', strtolower(json_encode($steps['vps'])));
    }

    public function test_the_page_renders_the_hosting_setup_guide(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(SystemHealth::class)
            ->assertOk()
            ->assertSee('Hosting &amp; background setup', false)
            ->assertSee('schedule:run');
    }
}
