<?php

namespace Tests\Feature;

use App\Livewire\Admin\Merchants as AdminMerchants;
use App\Livewire\BecomeMerchant;
use App\Models\KycVerification;
use App\Models\Merchant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Merchants\MerchantException;
use App\Services\Merchants\MerchantService;
use App\Support\MerchantSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 3.1 — becoming a merchant. KYB-gated application, admin
 * approval grants the additive merchant role; suspend removes it.
 */
class MerchantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(MerchantSettings::FLAG, true, 'merchants');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function kybVerified(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        KycVerification::create([
            'user_id' => $user->id, 'level' => 3, 'provider' => 'manual',
            'status' => KycVerification::APPROVED, 'reference' => 'kyb:'.$user->id,
        ]);

        return $user;
    }

    private function service(): MerchantService
    {
        return app(MerchantService::class);
    }

    public function test_application_requires_kyb_verification(): void
    {
        $user = User::factory()->create(); // no L3

        $this->expectException(MerchantException::class);
        $this->service()->apply($user, ['business_name' => 'Acme Travel']);
    }

    public function test_application_requires_the_programme_to_be_open(): void
    {
        Setting::setValue(MerchantSettings::FLAG, false, 'merchants');

        $this->expectException(MerchantException::class);
        $this->service()->apply($this->kybVerified(), ['business_name' => 'Acme Travel']);
    }

    public function test_a_verified_user_can_apply_and_gets_a_unique_slug(): void
    {
        $a = $this->service()->apply($this->kybVerified(), ['business_name' => 'Acme Travel']);
        $b = $this->service()->apply($this->kybVerified(), ['business_name' => 'Acme Travel']);

        $this->assertSame(Merchant::PENDING, $a->status);
        $this->assertSame('acme-travel', $a->slug);
        $this->assertSame('acme-travel-2', $b->slug);
    }

    public function test_reapplying_returns_the_existing_application(): void
    {
        $user = $this->kybVerified();
        $a = $this->service()->apply($user, ['business_name' => 'Acme']);
        $b = $this->service()->apply($user, ['business_name' => 'Acme Again']);

        $this->assertSame($a->id, $b->id);
    }

    public function test_admin_approval_activates_and_grants_the_merchant_role(): void
    {
        $user = $this->kybVerified();
        $merchant = $this->service()->apply($user, ['business_name' => 'Acme']);

        $this->service()->approve($merchant, $this->admin());

        $this->assertSame(Merchant::ACTIVE, $merchant->fresh()->status);
        $this->assertTrue($user->fresh()->hasRole('merchant'));
    }

    public function test_suspending_removes_the_merchant_role(): void
    {
        $user = $this->kybVerified();
        $merchant = $this->service()->apply($user, ['business_name' => 'Acme']);
        $this->service()->approve($merchant, $this->admin());

        $this->service()->suspend($merchant->fresh(), $this->admin());

        $this->assertSame(Merchant::SUSPENDED, $merchant->fresh()->status);
        $this->assertFalse($user->fresh()->hasRole('merchant'));
    }

    public function test_the_become_merchant_page_walks_kyb_then_application(): void
    {
        // Not yet KYB-verified: submitting KYB creates a pending L3 check.
        $user = User::factory()->create(['is_active' => true]);
        Livewire::actingAs($user)->test(BecomeMerchant::class)
            ->set('regNumber', 'RC123456')
            ->call('submitKyb');
        $this->assertDatabaseHas('kyc_verifications', ['user_id' => $user->id, 'level' => 3, 'status' => 'pending']);

        // Once verified, applying creates the merchant.
        $verified = $this->kybVerified();
        Livewire::actingAs($verified)->test(BecomeMerchant::class)
            ->set('businessName', 'Verified Co')
            ->call('apply')
            ->assertSet('error', null);
        $this->assertDatabaseHas('merchants', ['owner_user_id' => $verified->id, 'business_name' => 'Verified Co']);
    }

    public function test_admin_merchants_page_is_admin_only_and_approves(): void
    {
        Livewire::actingAs(User::factory()->create())->test(AdminMerchants::class)->assertForbidden();

        $user = $this->kybVerified();
        $merchant = $this->service()->apply($user, ['business_name' => 'Acme']);

        Livewire::actingAs($this->admin())->test(AdminMerchants::class)
            ->call('approve', $merchant->id);

        $this->assertTrue($user->fresh()->hasRole('merchant'));
    }
}
