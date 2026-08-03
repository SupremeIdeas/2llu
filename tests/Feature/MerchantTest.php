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

    /** KYB-verified only (NOT yet eligible to migrate). */
    private function kybOnly(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        KycVerification::create([
            'user_id' => $user->id, 'level' => 3, 'provider' => 'manual',
            'status' => KycVerification::APPROVED, 'reference' => 'kyb:'.$user->id,
        ]);

        return $user;
    }

    /** KYB-verified AND eligible (fast-route enrollment marked paid). */
    private function kybVerified(): User
    {
        $user = $this->kybOnly();
        $user->forceFill(['merchant_enrollment_paid_at' => now()])->save();

        return $user;
    }

    private function service(): MerchantService
    {
        return app(MerchantService::class);
    }

    public function test_application_no_longer_requires_kyb_up_front(): void
    {
        // BUILD-4 §1: KYB is deferred to payout — an ELIGIBLE user with NO
        // identity verification can now apply and get a pending storefront.
        $user = User::factory()->create(['is_active' => true]);
        $user->forceFill(['merchant_enrollment_paid_at' => now()])->save(); // eligible, no KYB

        $merchant = $this->service()->apply($user, ['business_name' => 'Acme Travel']);

        $this->assertSame(Merchant::PENDING, $merchant->status);
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

    public function test_the_become_merchant_page_shows_unlock_then_application(): void
    {
        // Not eligible yet: the page shows the Unlock stage + the deferred-
        // verification note (no KYB form up front, §1).
        $user = User::factory()->create(['is_active' => true]);
        Livewire::actingAs($user)->test(BecomeMerchant::class)
            ->assertSee('Unlock')
            ->assertSee('when you first cash out');

        // Eligible with NO KYB: applying from the page creates the merchant (§1).
        $eligible = User::factory()->create(['is_active' => true]);
        $eligible->forceFill(['merchant_enrollment_paid_at' => now()])->save();
        Livewire::actingAs($eligible)->test(BecomeMerchant::class)
            ->set('businessName', 'Unlocked Co')
            ->call('apply')
            ->assertSet('error', null);
        $this->assertDatabaseHas('merchants', ['owner_user_id' => $eligible->id, 'business_name' => 'Unlocked Co']);
    }

    public function test_a_kyb_user_who_is_not_eligible_cannot_apply(): void
    {
        $this->expectException(MerchantException::class);
        $this->service()->apply($this->kybOnly(), ['business_name' => 'Acme']);
    }

    public function test_paying_the_fast_route_fee_unlocks_eligibility(): void
    {
        $user = $this->kybOnly();
        app(\App\Services\Wallet\WalletService::class)->credit($user, 100, 'USD', ['reference' => 'seed:'.$user->id]);

        $this->service()->payEnrollment($user->fresh());

        $this->assertTrue($this->service()->eligibility($user->fresh())['eligible']);
        // The $50 fee left the wallet exactly once.
        $this->assertEqualsWithDelta(50.0, (float) $user->fresh()->wallet->usd_balance, 0.001);
    }

    public function test_the_fast_route_needs_a_funded_wallet(): void
    {
        $user = $this->kybOnly(); // empty wallet

        $this->expectException(MerchantException::class);
        $this->service()->payEnrollment($user);
    }

    public function test_the_spend_threshold_unlocks_eligibility(): void
    {
        $user = $this->kybOnly();
        // Simulate lifetime spend by crediting then debiting (total_spent grows).
        $wallet = app(\App\Services\Wallet\WalletService::class);
        $wallet->credit($user, 100, 'USD', ['reference' => 'c:'.$user->id]);
        $wallet->debit($user, 80, 'USD', ['reference' => 'd:'.$user->id]); // total_spent = 80 >= 75

        $this->assertTrue($this->service()->eligibility($user->fresh())['eligible']);
    }

    public function test_the_referral_threshold_unlocks_eligibility(): void
    {
        \App\Models\Setting::setValue(\App\Support\MerchantSettings::MIN_REFERRALS, 2, 'merchants');
        $user = $this->kybOnly();
        \App\Models\Referral::create(['referrer_id' => $user->id, 'referred_id' => User::factory()->create()->id]);
        \App\Models\Referral::create(['referrer_id' => $user->id, 'referred_id' => User::factory()->create()->id]);

        $this->assertTrue($this->service()->eligibility($user->fresh())['eligible']);
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
