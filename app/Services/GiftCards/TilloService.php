<?php

namespace App\Services\GiftCards;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * NAARA-BUILD-18 (placeholder tier) — Tillo gift cards. Enterprise onboarding
 * (sales conversation, HMAC-signed requests). Adapter built + shipped
 * enabled=false; activate once an account + keys exist. Implements the existing
 * GiftCardProviderInterface. Tillo signs each request with an HMAC of
 * apiKey-clientRequestId-... — computed here from the configured secret.
 */
class TilloService implements GiftCardProviderInterface
{
    public function key(): string
    {
        return 'tillo';
    }

    public function available(): bool
    {
        return filled(config('services.tillo.api_key')) && filled(config('services.tillo.secret'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tillo.base_url'), '/'))
            ->withHeaders(['API-Key' => (string) config('services.tillo.api_key')])
            ->acceptJson()->timeout(20);
    }

    private function sign(string $endpoint, string $timestamp): string
    {
        // Tillo signature: HMAC-SHA256 over apiKey-endpoint-GET-timestamp (POST for
        // orders), keyed by the shared secret. Exact string composition is set at
        // activation against Tillo's current signing docs.
        $payload = implode('-', [config('services.tillo.api_key'), $endpoint, $timestamp]);

        return hash_hmac('sha256', $payload, (string) config('services.tillo.secret'));
    }

    public function getCatalogue(): array
    {
        $ts = (string) (now()->timestamp * 1000);
        $json = $this->client()->withHeaders(['Signature' => $this->sign('brand', $ts), 'Timestamp' => $ts])
            ->get('/brands')->json();

        return collect(data_get($json, 'data.brands', []))->map(fn ($b, $slug) => [
            'provider_product_id' => (string) $slug,
            'name' => (string) data_get($b, 'name', $slug),
            'currency' => (string) data_get($b, 'currency', 'GBP'),
        ])->values()->all();
    }

    public function getBalance(): float
    {
        $ts = (string) (now()->timestamp * 1000);
        $json = $this->client()->withHeaders(['Signature' => $this->sign('check-floats', $ts), 'Timestamp' => $ts])
            ->get('/check-floats')->json();

        return (float) data_get($json, 'data.floats.0.balance', 0);
    }

    public function preflight(): array
    {
        return ['ok' => $this->available(), 'message' => $this->available() ? 'ready' : 'enterprise account required'];
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        $ts = (string) (now()->timestamp * 1000);
        $json = $this->client()->withHeaders(['Signature' => $this->sign('digital-issue', $ts), 'Timestamp' => $ts])
            ->post('/digital/issue', [
                'client_request_id' => $reference,
                'brand' => $providerProductId,
                'face_value' => ['amount' => $amount, 'currency' => $currency],
                'delivery_method' => 'url',
            ])->json();

        return [
            'provider_ref' => (string) data_get($json, 'data.code', $reference),
            'code' => (string) data_get($json, 'data.code', ''),
            'url' => (string) data_get($json, 'data.url', ''),
            'status' => (string) data_get($json, 'status', 'ordered'),
        ];
    }
}
