<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Stripe (blueprint Section 14.2). Webhook verification follows Stripe's
 * scheme: the Stripe-Signature header carries `t=<ts>,v1=<sig>` where sig =
 * HMAC-SHA256 of "<ts>.<raw body>" with the endpoint's webhook secret, checked
 * constant-time within a timestamp tolerance. Amounts are in minor units.
 */
class StripeGateway implements PaymentGatewayInterface
{
    private const TOLERANCE_SECONDS = 300;

    public function name(): string
    {
        return 'stripe';
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withToken(config('services.stripe.secret_key'))
            ->asForm()
            ->post(rtrim(config('services.stripe.base_url'), '/').'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $reference,
                'success_url' => $meta['success_url'] ?? config('app.url').'/wallet',
                'cancel_url' => $meta['cancel_url'] ?? config('app.url').'/wallet',
                'metadata' => ['user_id' => $user->id],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => (int) round($amount * 100),
                        'product_data' => ['name' => 'NaaraSim wallet top-up'],
                    ],
                ]],
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) ($response['url'] ?? ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        $secret = (string) config('services.stripe.webhook_secret');
        if (! is_string($header) || $secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', $piece, 2), 2, null);
            $parts[trim((string) $k)] = trim((string) $v);
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if ($timestamp === null || $signature === null) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $object = $request->input('data.object', []);
        $type = $request->input('type');
        $success = in_array($type, ['checkout.session.completed', 'payment_intent.succeeded'], true)
            && in_array(data_get($object, 'payment_status', 'paid'), ['paid', 'succeeded', null], true);

        $amount = (float) (data_get($object, 'amount_total') ?? data_get($object, 'amount', 0)) / 100;

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) (data_get($object, 'client_reference_id') ?? data_get($object, 'id', '')),
            userId: ($uid = data_get($object, 'metadata.user_id')) !== null ? (int) $uid : null,
            amount: $amount,
            currency: strtoupper((string) data_get($object, 'currency', 'usd')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
