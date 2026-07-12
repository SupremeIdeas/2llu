<?php

namespace App\Services\SMS;

use App\Exceptions\OutOfStockException;

/**
 * SMS-Activate — global OTP/rental BACKUP to 5sim (blueprint Section 10.1).
 *
 * The router tries this only after 5sim for a non-US country. Its exact
 * endpoints (sms-activate.org/en/api2: key as a query param, numeric country
 * IDs mapped at sync) must be wired from the official docs at go-live — per
 * blueprint rule 1.1 we do NOT invent endpoints. Until SMSACTIVATE_API_KEY is
 * set and the endpoints are wired, this backup reports itself unavailable so
 * the router cleanly stays within the lane.
 */
class SmsActivateService implements SmsProviderInterface
{
    private function unavailable(): OutOfStockException
    {
        return new OutOfStockException('SMS-Activate backup is not configured yet.');
    }

    private function configured(): bool
    {
        return ! empty(config('services.smsactivate.api_key'));
    }

    public function priceFor(string $country, string $service): float
    {
        throw $this->unavailable();
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        throw $this->unavailable();
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        throw $this->unavailable();
    }

    public function check(string $providerRef): array
    {
        return ['status' => OtpStatus::PENDING, 'code' => null];
    }

    public function finish(string $providerRef): void
    {
    }

    public function cancel(string $providerRef): void
    {
    }

    public function balance(): float
    {
        return 0.0;
    }
}
