<?php

namespace App\Services\eSIM;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * eSIM Go — PRIMARY provider (blueprint Section 5.2). API v2.5 only.
 * Auth via X-API-Key on every request; sandbox toggled with `x-sandbox: on`.
 * The catalogue `price` field is the WHOLESALE cost (PRIVATE).
 */
class EsimGoService implements EsimProviderInterface
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.esimgo.base_url'), '/'))
            ->withHeaders(array_filter([
                'X-API-Key' => config('services.esimgo.api_key'),
                'x-sandbox' => config('services.esimgo.sandbox') ? 'on' : null,
            ]))
            ->acceptJson();
    }

    public function getCatalogue(): array
    {
        return $this->client()->get('/catalogue')->throw()->json() ?? [];
    }

    public function orderBundle(string $planId, int $qty = 1, ?string $iccid = null): array
    {
        return $this->client()->post('/orders', [
            'item' => $planId,
            'quantity' => $qty,
            'assign' => ! is_null($iccid),
            'iccids' => $iccid ? [$iccid] : [],
        ])->throw()->json() ?? [];
    }

    public function getEsim(string $iccid): array
    {
        return $this->client()->get("/esims/{$iccid}")->throw()->json() ?? [];
    }

    public function getUsage(string $iccid, string $bundleName): array
    {
        return $this->client()->get("/esims/{$iccid}/bundles/{$bundleName}")->throw()->json() ?? [];
    }

    public function revoke(string $iccid, string $bundleName): array
    {
        return $this->client()->delete("/esims/{$iccid}/bundles/{$bundleName}")->throw()->json() ?? [];
    }

    public function getBalance(): float
    {
        $org = $this->client()->get('/organisation')->throw()->json() ?? [];

        return (float) ($org['balance'] ?? 0);
    }
}
