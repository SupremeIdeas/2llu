<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 — Bitrefill gift cards, self-service tier. ~160 countries, real
 * public API, optionally crypto-funded. Ships enabled=false. Implements the
 * existing GiftCardProviderInterface — Basic auth (api_id:api_secret), JSON REST.
 */
class BitrefillService implements GiftCardProviderInterface
{
    public function key(): string
    {
        return 'bitrefill';
    }

    public function available(): bool
    {
        return filled(config('services.bitrefill.api_id')) && filled(config('services.bitrefill.api_secret'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.bitrefill.base_url'), '/'))
            ->withBasicAuth((string) config('services.bitrefill.api_id'), (string) config('services.bitrefill.api_secret'))
            ->acceptJson()->timeout(20);
    }

    public function getCatalogue(): array
    {
        $json = $this->client()->get('/products', ['limit' => 500])->json();

        return collect(data_get($json, 'data', $json))->map(fn ($p) => [
            'provider_product_id' => (string) data_get($p, 'id'),
            'name' => (string) data_get($p, 'name'),
            'country' => (string) data_get($p, 'countryCode', ''),
            'currency' => (string) data_get($p, 'currency', 'USD'),
            'min' => (float) data_get($p, 'range.min', data_get($p, 'packages.0.value', 0)),
            'max' => (float) data_get($p, 'range.max', 0),
        ])->all();
    }

    public function getBalance(): float
    {
        $json = $this->client()->get('/accounts/balance')->json();

        return (float) data_get($json, 'balance', 0);
    }

    public function preflight(): array
    {
        return ['ok' => $this->available(), 'message' => $this->available() ? 'ready' : 'not configured'];
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        $json = $this->client()->post('/invoices', [
            'products' => [[
                'product_id' => $providerProductId,
                'value' => $amount,
                'quantity' => 1,
            ]],
            'payment_method' => 'balance',
            'external_id' => $reference,
        ])->json();

        return [
            'provider_ref' => (string) data_get($json, 'id', data_get($json, 'orderId')),
            'code' => (string) data_get($json, 'orders.0.code', data_get($json, 'code', '')),
            'pin' => (string) data_get($json, 'orders.0.pin', ''),
            'status' => (string) data_get($json, 'status', 'ordered'),
        ];
    }
}
