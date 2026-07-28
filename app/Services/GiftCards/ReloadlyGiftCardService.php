<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reloadly Gift Cards — the PRIMARY Naara Gift provider (14k+ brands). OAuth2
 * client-credentials (the gift-card API is its own audience/host); sandbox by
 * default. Money-safety: the raw cost/discount/fee stays in cost_meta (private),
 * never surfaced — the storefront prices through PricingEngine.
 */
class ReloadlyGiftCardService implements GiftCardProviderInterface
{
    public function key(): string
    {
        return 'reloadly';
    }

    public function available(): bool
    {
        return filled(config('services.reloadly.client_id')) && filled(config('services.reloadly.client_secret'));
    }

    private function host(): string
    {
        return (bool) config('services.reloadly.sandbox', true)
            ? 'https://giftcards-sandbox.reloadly.com'
            : 'https://giftcards.reloadly.com';
    }

    /** Cached OAuth token (client-credentials, audience = the gift-card host). */
    private function token(): string
    {
        return Cache::remember('reloadly.giftcards.token', 3000, function () {
            $res = Http::asJson()->post((string) config('services.reloadly.auth_url'), [
                'client_id' => config('services.reloadly.client_id'),
                'client_secret' => config('services.reloadly.client_secret'),
                'grant_type' => 'client_credentials',
                'audience' => $this->host(),
            ])->throw();

            return (string) $res->json('access_token');
        });
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token())
            ->withHeaders(['Accept' => 'application/com.reloadly.giftcards-v1+json'])
            ->baseUrl($this->host())
            ->timeout(60);
    }

    public function getCatalogue(): array
    {
        $out = [];
        $page = 1;
        do {
            $res = $this->client()->get('/products', ['page' => $page, 'size' => 200])->throw();
            $content = (array) ($res->json('content') ?? $res->json() ?? []);
            foreach ($content as $p) {
                $out[] = $this->map($p);
            }
            $last = (bool) ($res->json('last') ?? true);
            $page++;
        } while (! $last && $page <= 50);

        return $out;
    }

    private function map(array $p): array
    {
        $brandName = data_get($p, 'brand.brandName') ?: ($p['productName'] ?? 'Gift card');

        return [
            'provider' => 'reloadly',
            'provider_product_id' => (string) ($p['productId'] ?? ''),
            'brand_key' => Str::slug($brandName),
            'brand_name' => $brandName,
            'country' => strtoupper((string) (data_get($p, 'country.isoName') ?? '')) ?: null,
            'currency' => (string) ($p['recipientCurrencyCode'] ?? '') ?: null,
            'denomination_type' => strtoupper((string) ($p['denominationType'] ?? 'FIXED')),
            'fixed_denominations' => array_values((array) ($p['fixedRecipientDenominations'] ?? [])),
            'min_amount' => $p['minRecipientDenomination'] ?? null,
            'max_amount' => $p['maxRecipientDenomination'] ?? null,
            'logo_url' => data_get($p, 'logoUrls.0'),
            'brand_color' => null,
            'category' => data_get($p, 'category.name'),
            'required_fields' => [['key' => 'email', 'label' => 'Recipient email', 'type' => 'email', 'required' => true]],
            'redeem_instruction' => data_get($p, 'redeemInstruction.verbose') ?: data_get($p, 'redeemInstruction.concise'),
            'cost_meta' => [
                'senderFee' => $p['senderFee'] ?? null,
                'discountPercentage' => $p['discountPercentage'] ?? null,
                'senderCurrencyCode' => $p['senderCurrencyCode'] ?? null,
                'fixedSenderDenominations' => $p['fixedSenderDenominations'] ?? null,
            ],
            'provider_enabled' => true,
        ];
    }

    public function getBalance(): float
    {
        try {
            return (float) ($this->client()->get('/accounts/balance')->json('balance') ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    public function preflight(): array
    {
        $blank = ['ok' => false, 'balance' => null, 'currency' => null, 'products' => null, 'error' => null];
        if (! $this->available()) {
            return ['error' => 'Add your Reloadly client ID + secret on Admin → API keys first.'] + $blank;
        }

        // Re-authenticate from scratch so a just-changed key is genuinely tested.
        Cache::forget('reloadly.giftcards.token');
        try {
            $client = $this->client();
            $bal = (array) $client->get('/accounts/balance')->throw()->json();
            $probe = $client->get('/products', ['page' => 1, 'size' => 1])->throw();
            $count = $probe->json('totalElements');

            return [
                'ok' => true,
                'balance' => isset($bal['balance']) ? (float) $bal['balance'] : null,
                'currency' => $bal['currencyCode'] ?? null,
                'products' => is_numeric($count) ? (int) $count : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['error' => $this->sanitize($e)] + $blank;
        }
    }

    /** Short, key-free error line for the admin readout. */
    private function sanitize(\Throwable $e): string
    {
        $msg = str_contains($e->getMessage(), 'Unauthenticated') || str_contains($e->getMessage(), '401')
            ? 'Authentication failed — check the key, secret and sandbox/live toggle.'
            : $e->getMessage();

        return mb_substr($msg, 0, 200);
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        try {
            $res = $this->client()->post('/orders', array_filter([
                'productId' => (int) $providerProductId,
                'quantity' => 1,
                'unitPrice' => $amount,
                'customIdentifier' => $reference,
                'senderName' => 'NaaraSim',
                'recipientEmail' => $fields['email'] ?? null,
            ], fn ($v) => $v !== null))->throw();

            $txId = (string) ($res->json('transactionId') ?? $res->json('id') ?? '');
            $status = strtoupper((string) ($res->json('status') ?? 'PROCESSING'));

            $receipt = ['delivery_type' => 'code'];
            $mapped = match ($status) {
                'SUCCESSFUL' => 'delivered',
                'FAILED', 'REFUNDED' => 'failed',
                default => 'processing',
            };

            if ($mapped === 'delivered' && $txId !== '') {
                // Redemption codes are a separate call on Reloadly.
                try {
                    $cards = (array) $this->client()->get("/orders/transactions/{$txId}/cards")->json();
                    $card = $cards[0] ?? [];
                    $receipt['code'] = $card['cardNumber'] ?? null;
                    $receipt['epin'] = $card['pinCode'] ?? ($card['cardNumber'] ?? null);
                } catch (\Throwable) {
                    $mapped = 'processing'; // code not ready yet — webhook/poll will fill it
                }
            }

            return ['provider_tx_id' => $txId, 'status' => $mapped, 'receipt' => $receipt];
        } catch (\Throwable $e) {
            throw new GiftCardProviderException('Reloadly order failed: '.$e->getMessage(), previous: $e);
        }
    }
}
