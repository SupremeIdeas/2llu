<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Flutterwave (blueprint Section 14.2). Webhook verification is a static
 * secret hash you set in the dashboard, sent back in the `verif-hash` header
 * (compared constant-time). Amounts are in major units.
 */
class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'flutterwave';
    }

    public function initialize(User $user, float $amount, string $currency, array $meta = []): array
    {
        $reference = 'NAARA-'.Str::uuid();

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->acceptJson()
            ->post(rtrim(config('services.flutterwave.base_url'), '/').'/payments', [
                'tx_ref' => $reference,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'redirect_url' => $meta['redirect_url'] ?? config('app.url').'/wallet',
                'customer' => ['email' => $user->email, 'name' => $user->name],
                'meta' => ['user_id' => $user->id],
            ])->throw()->json();

        return [
            'reference' => $reference,
            'redirect_url' => (string) data_get($response, 'data.link', ''),
        ];
    }

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('verif-hash');
        $secretHash = (string) config('services.flutterwave.secret_hash');

        if (! is_string($signature) || $secretHash === '') {
            return false;
        }

        return hash_equals($secretHash, $signature);
    }

    public function parseWebhook(Request $request): ?PaymentEvent
    {
        $data = $request->input('data', []);
        $success = (data_get($data, 'status') === 'successful');

        return new PaymentEvent(
            gateway: $this->name(),
            reference: (string) data_get($data, 'tx_ref', ''),
            userId: ($uid = data_get($data, 'meta.user_id') ?? data_get($data, 'meta.0.value')) !== null ? (int) $uid : null,
            amount: (float) data_get($data, 'amount', 0),
            currency: strtoupper((string) data_get($data, 'currency', 'NGN')),
            status: $success ? 'success' : 'failed',
            raw: $request->all(),
        );
    }
}
