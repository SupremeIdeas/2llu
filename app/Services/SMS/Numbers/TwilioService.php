<?php

namespace App\Services\SMS\Numbers;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\NumberProviderInterface;

/**
 * Twilio — PRIMARY for permanent numbers, voice, and real 2-way SMS
 * (blueprint Section 10.3), billed monthly (Part 14.4). The permanent lane and
 * its monthly-billing scheduler are built in a later module; this class fixes
 * the interface + container binding now. Exact endpoints are wired from
 * twilio.com/docs at go-live — we do not invent them (rule 1.1).
 */
class TwilioService implements NumberProviderInterface
{
    private function configured(): bool
    {
        return ! empty(config('services.twilio.account_sid'))
            && ! empty(config('services.twilio.auth_token'));
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        return [];
    }

    public function buyNumber(string $country, array $options = []): array
    {
        throw new OutOfStockException('Twilio permanent-number provisioning is not wired yet.');
    }

    public function sendSms(string $from, string $to, string $body): array
    {
        throw new OutOfStockException('Twilio messaging is not wired yet.');
    }

    public function releaseNumber(string $providerRef): void
    {
    }

    public function monthlyCost(string $country): float
    {
        return 0.0;
    }
}
