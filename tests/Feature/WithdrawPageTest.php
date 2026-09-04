<?php

namespace Tests\Feature;

use App\Livewire\Withdraw;
use App\Models\KycVerification;
use App\Models\PayoutAccount;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credits\CreditService;
use App\Services\Payouts\BankResolverInterface;
use App\Services\Payouts\PayoutAccountService;
use App\Services\Payouts\ResolvedAccount;
use App\Support\PayoutSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ROADMAP §Layer 1 — the customer cash-out page. KYC-L2 gated; manage payout
 * accounts (name resolved before saving) and withdraw withdrawable credits.
 */
class WithdrawPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::setValue(PayoutSettings::FLAG, true, 'payouts');
    }

    private function fakeResolver(): void
    {
        $resolver = new class implements BankResolverInterface
        {
            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function supports(string $country): bool
            {
                return $country === 'NG';
            }

            public function banks(string $country): array
            {
                return [['code' => '058', 'name' => 'GTBank']];
            }

            public function resolve(string $country, string $bankCode, string $accountNumber): ?ResolvedAccount
            {
                return $accountNumber === '0123456789'
                    ? new ResolvedAccount(accountName: 'JANE TRAVELLER', provider: 'paystack')
                    : null;
            }
        };
        $this->app->instance(PayoutAccountService::class, new PayoutAccountService([$resolver]));
    }

    private function verifiedUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        KycVerification::create([
            'user_id' => $user->id, 'level' => 2, 'provider' => 'manual',
            'status' => KycVerification::APPROVED, 'reference' => 'kyc:'.$user->id,
        ]);

        return $user;
    }

    public function test_unverified_users_are_sent_to_verify(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/rewards/withdraw')->assertRedirect(route('account.verify'));
    }

    public function test_verified_users_can_open_the_page(): void
    {
        $this->actingAs($this->verifiedUser())->get('/rewards/withdraw')->assertOk()->assertSee('Withdraw earnings');
    }

    public function test_adding_an_account_resolves_and_saves_it(): void
    {
        $this->fakeResolver();
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('country', 'NG')->set('bankCode', '058')->set('accountNumber', '0123456789')
            ->call('addAccount')
            ->assertSet('accountError', null);

        $this->assertDatabaseHas('payout_accounts', ['user_id' => $user->id, 'account_name' => 'JANE TRAVELLER']);
    }

    public function test_a_bad_account_number_shows_an_error(): void
    {
        $this->fakeResolver();
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('country', 'NG')->set('bankCode', '058')->set('accountNumber', '0000000000')
            ->call('addAccount')
            ->assertSet('accountError', fn ($v) => $v !== null);

        $this->assertDatabaseCount('payout_accounts', 0);
    }

    public function test_a_verified_user_can_request_a_withdrawal(): void
    {
        $user = $this->verifiedUser();
        app(CreditService::class)->rewardReferral($user, 1, 1000); // $10 withdrawable
        $account = PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountId', $account->id)->set('amountUsd', 10)
            ->call('withdraw')
            ->assertSet('withdrawError', null);

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $user->id, 'source_bucket' => 'referral_credits', 'status' => 'pending',
        ]);
    }

    public function test_paypal_toggle_only_shows_when_paypal_is_configured(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->get('/rewards/withdraw')->assertDontSee('PayPal');

        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $this->actingAs($user)->get('/rewards/withdraw')->assertSee('PayPal');
    }

    public function test_a_matching_paypal_email_pair_saves_a_verified_account(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountType', 'paypal')
            ->set('paypalEmail', 'jane@example.com')
            ->set('paypalEmailConfirm', 'jane@example.com')
            ->call('addPaypalAccount')
            ->assertSet('accountError', null);

        $this->assertDatabaseHas('payout_accounts', [
            'user_id' => $user->id, 'type' => 'paypal', 'provider' => 'paypal',
            'account_number' => 'jane@example.com', 'is_verified' => true,
        ]);
    }

    public function test_mismatched_paypal_emails_are_rejected(): void
    {
        config(['services.paypal.client_id' => 'id', 'services.paypal.client_secret' => 'secret']);
        $user = $this->verifiedUser();

        Livewire::actingAs($user)->test(Withdraw::class)
            ->set('accountType', 'paypal')
            ->set('paypalEmail', 'jane@example.com')
            ->set('paypalEmailConfirm', 'typo@example.com')
            ->call('addPaypalAccount')
            ->assertSet('accountError', fn ($v) => $v !== null);

        $this->assertDatabaseCount('payout_accounts', 0);
    }
}
