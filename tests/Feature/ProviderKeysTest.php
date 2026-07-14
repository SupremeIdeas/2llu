<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProviderKeys as ProviderKeysComponent;
use App\Models\Setting;
use App\Models\User;
use App\Support\ProviderKeys;
use App\Support\ProviderStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin-managed API credentials (blueprint Sections 15 & 17.4, money rule 10).
 */
class ProviderKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        ProviderKeys::flush();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $user->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_a_saved_key_overrides_config_and_flips_the_provider_active(): void
    {
        config(['services.getatext.api_key' => null]);
        $this->assertSame('Coming Soon', ProviderStatus::label('getatext'));

        ProviderKeys::save(['getatext_api_key' => 'sk_live_admin_saved']);
        ProviderKeys::applyToConfig();

        $this->assertSame('sk_live_admin_saved', config('services.getatext.api_key'));
        $this->assertSame('Active', ProviderStatus::label('getatext'));
    }

    public function test_a_blank_field_leaves_the_stored_key_untouched(): void
    {
        ProviderKeys::save(['fivesim_api_key' => 'jwt-original']);
        ProviderKeys::save(['fivesim_api_key' => '']); // blank = no-op
        ProviderKeys::applyToConfig();

        $this->assertSame('jwt-original', config('services.fivesim.api_key'));
    }

    public function test_keys_are_encrypted_at_rest_and_never_stored_in_plaintext(): void
    {
        ProviderKeys::save(['stripe_secret_key' => 'sk_live_topsecret_value']);

        // The raw settings row (bypassing the Eloquent cast) must not contain
        // the plaintext secret — it is encrypted at rest.
        $raw = \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', ProviderKeys::SETTING_KEY)
            ->value('value');
        $this->assertStringNotContainsString('sk_live_topsecret_value', (string) $raw);
    }

    public function test_preview_is_masked_and_reveals_only_the_last_four(): void
    {
        ProviderKeys::save(['paystack_secret_key' => 'sk_live_1234ABCD']);
        ProviderKeys::applyToConfig();

        $preview = ProviderKeys::preview('paystack_secret_key');
        $this->assertStringEndsWith('ABCD', $preview);
        $this->assertStringNotContainsString('1234', $preview);
        $this->assertStringContainsString('•', $preview);
    }

    public function test_the_admin_page_saves_keys_and_never_echoes_them_back(): void
    {
        Livewire::actingAs($this->superAdmin())->test(ProviderKeysComponent::class)
            ->set('inputs.esimgo_api_key', 'esimgo-live-key')
            ->call('save')
            ->assertSet('inputs.esimgo_api_key', '') // cleared, not echoed
            ->assertSee('take effect immediately');

        ProviderKeys::applyToConfig();
        $this->assertSame('esimgo-live-key', config('services.esimgo.api_key'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'providers.keys_updated']);
    }

    public function test_a_non_super_admin_cannot_reach_the_api_keys_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->forceFill([
            'two_factor_secret' => encrypt('SECRETKEY'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        // super_admin-only route -> 403 for a plain admin.
        $this->actingAs($admin)->get('/adminmaster/api-keys')->assertForbidden();

        // super_admin gets in.
        $this->actingAs($this->superAdmin())->get('/adminmaster/api-keys')->assertOk();
    }
}
