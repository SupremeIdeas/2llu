<?php

namespace Tests\Feature;

use App\Livewire\Wallet;
use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The wallet page now surfaces payout/withdrawal setup directly (owner
 * request: "make the payout area very easy to locate in wallet") instead of
 * only at the separate /rewards/withdraw route. The KYC-L2 gate shown here is
 * a display decision only — WithdrawalService::request() independently
 * re-enforces it server-side regardless of which page renders the form.
 */
class WalletPayoutSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unverified_user_sees_a_verify_identity_prompt_not_the_withdraw_form(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('Verify your identity to set up a bank account and withdraw earnings')
            ->assertDontSee('Withdraw earnings'); // the embedded Withdraw component's own heading
    }

    public function test_a_kyc_l2_verified_user_sees_the_embedded_withdraw_form(): void
    {
        $user = User::factory()->create();
        KycVerification::create([
            'user_id' => $user->id, 'level' => KycVerification::L2,
            'provider' => 'manual', 'status' => KycVerification::APPROVED,
            'checks' => [], 'reference' => 'test-kyc-'.$user->id,
        ]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertDontSee('Verify your identity to set up a bank account')
            ->assertSee('Withdraw earnings');
    }

    public function test_a_default_payout_account_shows_in_the_collapsed_summary(): void
    {
        $user = User::factory()->create();
        KycVerification::create([
            'user_id' => $user->id, 'level' => KycVerification::L2,
            'provider' => 'manual', 'status' => KycVerification::APPROVED,
            'checks' => [], 'reference' => 'test-kyc-'.$user->id,
        ]);
        PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'bank_name' => 'GTBank', 'account_number' => '0123456789',
            'account_name' => 'Test User', 'provider' => 'flutterwave',
            'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Wallet::class)
            ->assertSee('GTBank')
            ->assertDontSee('Set up your bank account to withdraw earnings');
    }
}
