<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cryptomus (blueprint Section 14.2 — crypto rail). Documented API: create a
 * payment (merchant UUID + a `sign` header = md5(base64(json_body) . api_key))
 * and redirect to the returned URL. The webhook carries the same `sign` in its
 * body, computed over the payload minus `sign` (money-safety rule 9) — verified
 * before trust. USD-priced; crypto settles to the USD wallet. Safe when off.
 */
class CryptomusGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'cryptomus';
    }

    private function configured(): bool
    {
        return ! empty(config('services.cryptomus.merchant_id'))
            && ! empty(config('services.cryptomus.api_key'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.cryptomus.base_url', 'https://api.cryptomus.com'), '/');
    }

    /** Cryptomus signs md5(base64_encode(json) . api_key) with unescaped JSON. */
    private function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return md5(base64_encode($json).(string) config('services.cryptomus.api_key'));
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $payload = [
            'amount' => (string) round($amount, 2),
            'currency' => strtoupper($currency),
            // order_id carries "<user_id>:<reference>" so the webhook credits the
            // right wallet; Cryptomus echoes order_id back in the callback.
            'order_id' => $user->id.':'.$reference,
            'url_callback' => route('webhooks.payments', 'cryptomus'),
            'url_return' => $meta['return_url'] ?? config('app.url').'/wallet',
        ];

        $response = Http::withHeaders([
            'merchant' => (string) config('services.cryptomus.merchant_id'),
            'sign' => $this->sign($payload),
            'Content-Type' => 'application/json',
        ])->acceptJson()->post($this->base().'/v1/payment', $payload)->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) data_get($response, 'result.url', ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $data = $request->json()->all();
        $signature = (string) ($data['sign'] ?? '');
        if ($signature === '') {
            return false;
        }
        unset($data['sign']);

        return hash_equals($this->sign($data), $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $status = (string) $request->input('status', '');
        $success = in_array($status, ['paid', 'paid_over'], true);

        [$userId, $reference] = array_pad(explode(':', (string) $request->input('order_id', ''), 2), 2, null);

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) ($reference ?? ''),
            userId: $userId !== null && $userId !== '' ? (int) $userId : null,
            amount: (float) $request->input('amount', 0),
            currency: strtoupper((string) $request->input('currency', 'USD')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
