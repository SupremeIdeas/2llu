<?php

namespace Tests\Feature;

use App\Jobs\AlertAdminJob;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\Payments\RefundException;
use App\Services\Payments\RefundService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BUILD-2 §7.1 — admin-triggered refunds. Provider-first, ledger-reversing,
 * idempotent, and never silently loss-making.
 */
class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    private function topUp(User $user, string $gateway, string $ref, float $amount, string $currency = 'USD')
    {
        // Simulate the credited top-up exactly as CreditWalletJob writes it.
        return app(WalletService::class)->credit($user, $amount, $currency, [
            'reference' => "topup:{$gateway}:{$ref}",
            'description' => "Wallet top-up via {$gateway}",
        ]);
    }

    public function test_paystack_refund_reverses_the_wallet_and_records_it(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.base_url' => 'https://api.paystack.co']);
        Http::fake(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 'RF_1']])]);

        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-1', 50.0);
        $this->assertSame('50.0000', (string) $user->wallet->fresh()->usd_balance);

        $refund = app(RefundService::class)->refund($topup, null, 'duplicate charge');

        $this->assertSame(PaymentRefund::STATUS_DONE, $refund->status);
        $this->assertSame('RF_1', $refund->provider_refund_ref);
        $this->assertSame('0.0000', (string) $user->wallet->fresh()->usd_balance); // reversed
        $this->assertDatabaseHas('wallet_transactions', ['reference' => 'refund-reversal:paystack:PS-REF-1', 'type' => 'debit']);
    }

    public function test_a_top_up_can_only_be_refunded_once(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 'RF_2']])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-2', 20.0);

        app(RefundService::class)->refund($topup, null, 'x');

        $this->expectException(RefundException::class);
        app(RefundService::class)->refund($topup, null, 'again');
    }

    public function test_refund_is_refused_when_the_user_already_spent_the_funds(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => ['id' => 'RF_3']])]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-3', 30.0);

        // Spend most of it.
        app(WalletService::class)->debit($user, 25.0, 'USD', ['reference' => 'spend:1', 'description' => 'bought an eSIM']);

        try {
            app(RefundService::class)->refund($topup, null, 'wants money back');
            $this->fail('Expected RefundException');
        } catch (RefundException $e) {
            $this->assertStringContainsStringIgnoringCase('spent', $e->getMessage());
        }

        // Wallet untouched, no Paystack refund attempted, no refund row.
        $this->assertSame('5.0000', (string) $user->wallet->fresh()->usd_balance);
        Http::assertNothingSent();
        $this->assertSame(0, PaymentRefund::count());
    }

    public function test_a_declined_gateway_refund_does_not_touch_the_wallet(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_x']);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Transaction not refundable'], 400)]);
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'paystack', 'PS-REF-4', 40.0);

        try {
            app(RefundService::class)->refund($topup, null, 'x');
            $this->fail('Expected RefundException');
        } catch (RefundException $e) {
            $this->assertStringContainsStringIgnoringCase('declined', $e->getMessage());
        }

        $this->assertSame('40.0000', (string) $user->wallet->fresh()->usd_balance); // unchanged
        $this->assertSame(PaymentRefund::STATUS_FAILED, PaymentRefund::first()->status);
    }

    public function test_a_crypto_top_up_is_flagged_for_a_manual_refund_and_alerts(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $topup = $this->topUp($user, 'nowpayments', 'NP-REF-1', 15.0); // no RefundableGateway

        $refund = app(RefundService::class)->refund($topup, null, 'chargeback risk');

        $this->assertSame(PaymentRefund::STATUS_MANUAL, $refund->status);
        $this->assertSame('15.0000', (string) $user->wallet->fresh()->usd_balance); // NOT auto-moved
        Queue::assertPushed(AlertAdminJob::class);
    }
}
