<?php

namespace Tests\Feature;

use App\Events\PayoutReversed;
use App\Events\PayoutSettled;
use App\Models\ErrorLog;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Services\Payouts\PayoutService;
use App\Services\Payouts\PayoutTransferResult;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * ROADMAP §Layer 0.2 — the payout engine. Same money-safety discipline as
 * WalletService: idempotent, never re-sends, webhook-confirmed truth, and a
 * failed transfer reverses the hold + alerts (never blind-retries).
 */
class PayoutEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /** A fake PSP whose send() returns a scripted result and records calls. */
    private function gateway(string $sendStatus = 'processing', ?string $fail = null): PayoutGatewayInterface
    {
        return new class($sendStatus, $fail) implements PayoutGatewayInterface
        {
            public int $sends = 0;

            public function __construct(private string $sendStatus, private ?string $fail)
            {
            }

            public function name(): string
            {
                return 'paystack';
            }

            public function available(): bool
            {
                return true;
            }

            public function createRecipient(PayoutAccount $account): string
            {
                return 'RCP_test';
            }

            public function sendTransfer(PayoutRequest $request, PayoutAccount $account): PayoutTransferResult
            {
                $this->sends++;

                return new PayoutTransferResult(status: $this->sendStatus, providerRef: 'TRF_1', failureReason: $this->fail);
            }

            public function verifyWebhook(\Illuminate\Http\Request $request): bool
            {
                return true;
            }

            public function parseWebhook(\Illuminate\Http\Request $request): ?\App\Services\Payouts\PayoutEvent
            {
                return null;
            }
        };
    }

    private function account(User $user): PayoutAccount
    {
        return PayoutAccount::create([
            'user_id' => $user->id, 'type' => 'bank', 'country' => 'NG', 'currency' => 'NGN',
            'bank_code' => '058', 'account_number' => '0123456789', 'account_name' => 'JANE T.',
            'provider' => 'paystack', 'is_verified' => true, 'is_default' => true,
        ]);
    }

    private function service(PayoutGatewayInterface $gateway): PayoutService
    {
        $service = new PayoutService([$gateway]);
        $this->app->instance(PayoutService::class, $service); // so SendPayoutJob uses the fake

        return $service;
    }

    public function test_create_request_is_idempotent_by_reference(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = $this->service($this->gateway());

        $a = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:1');
        $b = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:1');

        $this->assertSame($a->id, $b->id);
        $this->assertDatabaseCount('payout_requests', 1);
    }

    public function test_it_refuses_an_account_that_is_not_the_users(): void
    {
        $user = User::factory()->create();
        $other = $this->account(User::factory()->create());
        $svc = $this->service($this->gateway());

        $this->expectException(\App\Services\Payouts\PayoutException::class);
        $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $other, 'wd:x');
    }

    public function test_admin_approval_sends_and_marks_processing(): void
    {
        Event::fake([PayoutSettled::class, PayoutReversed::class]);
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $account = $this->account($user);
        $gateway = $this->gateway('processing');
        $svc = $this->service($gateway);

        $req = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:2');
        $svc->approve($req, $admin);

        $this->assertSame(PayoutRequest::PROCESSING, $req->fresh()->status);
        $this->assertSame('RCP_test', $account->fresh()->provider_recipient_ref); // recipient cached once
        $this->assertSame(1, $gateway->sends);
    }

    public function test_non_admin_cannot_approve(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = $this->service($this->gateway());
        $req = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:3');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->approve($req, User::factory()->create());
    }

    public function test_a_failed_transfer_reverses_and_alerts(): void
    {
        Event::fake([PayoutReversed::class]);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = $this->service($this->gateway('failed', 'Insufficient balance'));

        $req = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:4');
        $svc->send($req);

        $this->assertSame(PayoutRequest::FAILED, $req->fresh()->status);
        $this->assertStringContainsString('Insufficient balance', $req->fresh()->failure_reason);
        Event::assertDispatched(PayoutReversed::class);
        $this->assertDatabaseHas('error_logs', ['code' => 'payout_failed']);
    }

    public function test_send_never_resends_a_finalised_request(): void
    {
        $user = User::factory()->create();
        $account = $this->account($user);
        $gateway = $this->gateway('processing');
        $svc = $this->service($gateway);

        $req = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:5');
        $svc->send($req);          // 1st send → processing
        $svc->send($req->fresh()); // must be a no-op

        $this->assertSame(1, $gateway->sends);
    }

    public function test_confirm_marks_paid_once_and_fires_settled(): void
    {
        Event::fake([PayoutSettled::class]);
        $user = User::factory()->create();
        $account = $this->account($user);
        $svc = $this->service($this->gateway());
        $req = $svc->createRequest($user, 10.0, 'USD', 'referral_credits', $account, 'wd:6');

        $svc->confirm($req, 'TRF_9');
        $svc->confirm($req->fresh(), 'TRF_9'); // idempotent

        $this->assertSame(PayoutRequest::PAID, $req->fresh()->status);
        $this->assertNotNull($req->fresh()->settled_at);
        Event::assertDispatchedTimes(PayoutSettled::class, 1);
    }

    public function test_the_paystack_payout_webhook_settles_the_request(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_payout']);
        $user = User::factory()->create();
        $account = $this->account($user);
        // A processing request awaiting confirmation.
        PayoutRequest::create([
            'user_id' => $user->id, 'payout_account_id' => $account->id, 'amount' => 10.0,
            'currency' => 'NGN', 'source_bucket' => 'referral_credits', 'status' => PayoutRequest::PROCESSING,
            'provider' => 'paystack', 'provider_ref' => 'TRF_1', 'reference' => 'wd:hook',
        ]);

        $body = json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'wd:hook', 'transfer_code' => 'TRF_1']]);
        $signature = hash_hmac('sha512', $body, 'sk_test_payout');

        $this->call('POST', '/webhooks/payouts/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();

        $this->assertSame(PayoutRequest::PAID, PayoutRequest::where('reference', 'wd:hook')->first()->status);
    }

    public function test_a_bad_payout_webhook_signature_is_rejected(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_payout']);
        $body = json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'wd:hook']]);

        $this->call('POST', '/webhooks/payouts/paystack', [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => 'wrong', 'CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);
    }
}
