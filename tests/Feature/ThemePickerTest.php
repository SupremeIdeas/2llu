<?php

namespace Tests\Feature;

use App\Livewire\Admin\ThemePicker;
use App\Models\Setting;
use App\Models\User;
use App\Support\ThemePreset;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemePresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 2 §4. The admin theme picker: select-and-apply,
 * admin-gated, server-validated. Applying is the ONLY write it makes (the active
 * slug + cache bust) — no business logic, no token editing.
 */
class ThemePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ThemePresetSeeder::class);
        ThemePreset::bust();
    }

    public function test_a_non_admin_gets_a_403(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ThemePicker::class)->assertStatus(403);
    }

    public function test_an_admin_sees_all_fifteen_presets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->assertStatus(200)
            ->assertViewHas('presets', fn ($p) => $p->count() === 15)
            ->assertSee('Naara Official')
            ->assertSee('Aurora Shift');
    }

    public function test_applying_a_preset_writes_the_setting_and_busts_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->call('apply', 'midnight-signal');

        $this->assertSame('midnight-signal', Setting::getValue(ThemePreset::SETTING_KEY));
        // The cached active preset reflects the change immediately.
        $this->assertSame('midnight-signal', ThemePreset::slug());
    }

    public function test_applying_an_unknown_slug_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)->test(ThemePicker::class)
            ->call('apply', 'not-a-real-theme');

        // Nothing was written — still the default.
        $this->assertNotSame('not-a-real-theme', Setting::getValue(ThemePreset::SETTING_KEY));
        $this->assertSame('naara-official', ThemePreset::slug());
    }
}
