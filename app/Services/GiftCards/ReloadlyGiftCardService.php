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
}
