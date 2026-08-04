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
}
