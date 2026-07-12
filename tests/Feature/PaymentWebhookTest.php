<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    /** Post a raw JSON body so signature bytes match exactly. */
    private function postRaw(string $uri, array $payload, array $headers = []): TestResponse
    {
        $content = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    private function paystackPayload(User $user, string $ref = 'NAARA-PS-1'): array
    {
        return [
            'event' => 'charge.success',
            'data' => [
                'reference' => $ref,
                'status' => 'success',
                'amount' => 500000, // kobo -> 5000.00
                'currency' => 'NGN',
                'metadata' => ['user_id' => $user->id],
            ],
        ];
    }

    private function paystackSign(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), (string) config('services.paystack.secret_key'));
    }

    public function test_paystack_valid_signature_credits_the_wallet_once_even_if_delivered_twice(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        $user = User::factory()->create();
        $payload = $this->paystackPayload($user);
        $sig = $this->paystackSign($payload);

        // Deliver twice (webhook retries / at-least-once delivery).
        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();
        $this->postRaw('/webhooks/payments/paystack', $payload, ['x-paystack-signature' => $sig])->assertOk();

        $this->assertSame('5000.00', (string) $user->wallet->fresh()->ngn_balance);
        $this->assertSame(1, WalletTransaction::where('reference', 'topup:paystack:NAARA-PS-1')->count());
    }

    public function test_paystack_invalid_signature_is_rejected_and_nothing_is_credited(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_secret']);
        $user = User::factory()->create();

        $this->postRaw('/webhooks/payments/paystack', $this->paystackPayload($user), [
            'x-paystack-signature' => 'deadbeef',
        ])->assertStatus(401);

        $this->assertNull($user->wallet); // no wallet ever created
        $this->assertDatabaseHas('webhook_logs', ['provider' => 'paystack', 'verified' => false]);
    }

    public function test_flutterwave_verif_hash_credits_on_match(): void
    {
        config(['services.flutterwave.secret_hash' => 'flw-secret-hash']);
        $user = User::factory()->create();
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'NAARA-FLW-1', 'status' => 'successful',
                'amount' => 12.5, 'currency' => 'USD', 'meta' => ['user_id' => $user->id],
            ],
        ];

        $this->postRaw('/webhooks/payments/flutterwave', $payload, ['verif-hash' => 'flw-secret-hash'])->assertOk();
        $this->assertSame('12.5000', (string) $user->wallet->fresh()->usd_balance);

        // Wrong hash rejected.
        $other = User::factory()->create();
        $this->postRaw('/webhooks/payments/flutterwave', [
            'event' => 'charge.completed',
            'data' => ['tx_ref' => 'X', 'status' => 'successful', 'amount' => 5, 'currency' => 'USD', 'meta' => ['user_id' => $other->id]],
        ], ['verif-hash' => 'wrong'])->assertStatus(401);
        $this->assertNull($other->wallet);
    }

    public function test_stripe_signature_scheme_is_verified_and_credits(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::factory()->create();
        $payload = [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_1', 'client_reference_id' => 'NAARA-ST-1', 'payment_status' => 'paid',
                'amount_total' => 2500, 'currency' => 'usd', 'metadata' => ['user_id' => $user->id],
            ]],
        ];
        $content = json_encode($payload);
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$content}", 'whsec_test');

        $this->postRaw('/webhooks/payments/stripe', $payload, ['Stripe-Signature' => "t={$t},v1={$sig}"])->assertOk();
        $this->assertSame('25.0000', (string) $user->wallet->fresh()->usd_balance);
    }

    public function test_stripe_rejects_a_stale_timestamp(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test']);
        $user = User::factory()->create();
        $payload = ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'client_reference_id' => 'OLD', 'payment_status' => 'paid', 'amount_total' => 2500,
            'currency' => 'usd', 'metadata' => ['user_id' => $user->id],
        ]]];
        $content = json_encode($payload);
        $t = time() - 999; // outside tolerance
        $sig = hash_hmac('sha256', "{$t}.{$content}", 'whsec_test');

        $this->postRaw('/webhooks/payments/stripe', $payload, ['Stripe-Signature' => "t={$t},v1={$sig}"])->assertStatus(401);
        $this->assertNull($user->wallet);
    }

    public function test_unknown_gateway_is_404(): void
    {
        $this->postRaw('/webhooks/payments/bitcoin', [])->assertNotFound();
    }
}
