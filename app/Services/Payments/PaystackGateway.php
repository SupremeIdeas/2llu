<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Paystack (blueprint Section 14.2). Amounts are in minor units (kobo/cents)
 * on the wire; normalised to major units here. Webhook signature is
 * HMAC-SHA512 of the raw body with the secret key (header x-paystack-signature).
 */
class PaystackGateway implements PaymentGatewayInterface, RefundableGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    /**
     * Refund a settled charge (BUILD-2 §7.1). Paystack's POST /refund takes the
     * original transaction reference; an amount (in kobo) makes it partial, else
     * it's a full refund. Bounded timeout like every provider call. Returns the
     * provider refund id on success, a safe error otherwise — the caller only
     * reverses the wallet ledger when this is ok.
     */
    public function refund(string $reference, float $amount, string $currency, array $context = []): RefundResult
    {
        $secret = (string) config('services.paystack.secret_key');
        if ($secret === '') {
            return RefundResult::fail('Paystack is not configured.');
        }

        try {
            $res = Http::withToken($secret)->acceptJson()
                ->timeout(15)->connectTimeout(3)
                ->post(rtrim((string) config('services.paystack.base_url'), '/').'/refund', [
                    'transaction' => $reference,
                    'amount' => (int) round($amount * 100), // kobo
                ]);
        } catch (\Throwable $e) {
            return RefundResult::fail('Could not reach Paystack to refund.');
        }

        if (! $res->successful() || data_get($res->json(), 'status') !== true) {
            return RefundResult::fail((string) (data_get($res->json(), 'message') ?: 'Paystack refused the refund.'));
        }

        return RefundResult::ok((string) data_get($res->json(), 'data.id'));
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->acceptJson()
            ->post(rtrim(config('services.paystack.base_url'), '/').'/transaction/initialize', [
                'email' => $user->email,
                'amount' => (int) round($amount * 100), // kobo
                'currency' => strtoupper($currency),
                'reference' => $reference,
                'metadata' => ['user_id' => $user->id] + $meta,
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) data_get($response, 'data.authorization_url', ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');
        $secret = (string) config('services.paystack.secret_key');

        // An empty secret must never validate: hash_hmac(..., '') is computable
        // by anyone (the key is "known" to be blank), so without this guard an
        // unconfigured Paystack could have forged webhooks accepted and credit
        // an attacker's own wallet for free.
        if (! is_string($signature) || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $data = $request->input('data', []);
        $success = ($request->input('event') === 'charge.success')
            && (data_get($data, 'status') === 'success');

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) data_get($data, 'reference', ''),
            userId: ($uid = data_get($data, 'metadata.user_id')) !== null ? (int) $uid : null,
            amount: (float) data_get($data, 'amount', 0) / 100, // kobo -> major
            currency: strtoupper((string) data_get($data, 'currency', 'NGN')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
