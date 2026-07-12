<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\NumberProviderInterface;

/**
 * Telnyx — permanent/voice BACKUP to Twilio, typically cheaper (blueprint
 * Section 10.2). Same interface; the router tries it after Twilio when Twilio
 * has no number in the requested region. Exact endpoints
 * (/available_phone_numbers, /number_orders, /messages) are wired from
 * developers.telnyx.com at go-live — not invented here (rule 1.1).
 */
class TelnyxService implements NumberProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.telnyx.api_key'));
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        return [];
    }

    public function buyNumber(string $country, array $options = []): array
    {
        throw new OutOfStockException('Telnyx permanent-number provisioning is not wired yet.');
    }

    public function sendSms(string $from, string $to, string $body): array
    {
        throw new OutOfStockException('Telnyx messaging is not wired yet.');
    }

    public function releaseNumber(string $providerRef): void
    {
    }

    public function monthlyCost(string $country): float
    {
        return 0.0;
    }
}
