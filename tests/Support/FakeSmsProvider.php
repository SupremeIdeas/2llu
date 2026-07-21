<?php

namespace Tests\Support;

use App\Services\SMS\OtpStatus;
use App\Services\SMS\SmsProviderInterface;
use Throwable;

/**
 * Configurable OTP/rental provider double for SmsNumberRouter and OTP-job
 * tests. Records finish()/cancel() calls so tests can assert 5sim rating
 * discipline (finish on received, cancel on timeout).
 */
class FakeSmsProvider implements SmsProviderInterface
{
    public int $buyCalls = 0;

    public int $finishCalls = 0;

    public int $cancelCalls = 0;

    public bool $fullRent = false;

    public function __construct(
        private float|Throwable $price = 1.0,
        private array $buyResponse = ['provider_ref' => 'REF-1', 'number' => '15550001111', 'cost' => 1.0, 'status' => OtpStatus::PENDING],
        private array $checkResponse = ['status' => OtpStatus::PENDING, 'code' => null],
    ) {
    }

    public function supportsFullRent(): bool
    {
        return $this->fullRent;
    }

    public function priceFor(string $country, string $service): float
    {
        if ($this->price instanceof Throwable) {
            throw $this->price;
        }

        return $this->price;
    }

    public function buyOtp(string $country, string $service, array $options = []): array
    {
        $this->buyCalls++;

        return $this->buyResponse;
    }

    public function buyRental(string $country, string $service, array $options = []): array
    {
        $this->buyCalls++;

        return $this->buyResponse;
    }

    public function check(string $providerRef): array
    {
        return $this->checkResponse;
    }

    public function finish(string $providerRef): void
    {
        $this->finishCalls++;
    }

    public function cancel(string $providerRef): void
    {
        $this->cancelCalls++;
    }

    public function balance(): float
    {
        return 100.0;
    }
}
