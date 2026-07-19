<?php

namespace Tests\Support;

use App\Exceptions\OutOfStockException;
use App\Services\SMS\NumberProviderInterface;

/**
 * Test double for a permanent-number provider (Twilio/Telnyx) — no network.
 * Deterministic search + provisioning, with a switch to simulate a provider
 * failure, and it records releases so the billing/orphan tests can assert them.
 */
class FakePermanentProvider implements NumberProviderInterface
{
    /** @var list<string> */
    public array $released = [];

    /**
     * @param  list<array{number:string, locality:string}>  $results
     */
    public function __construct(
        private float $cost = 1.00,
        private array $results = [['number' => '+15550001234', 'locality' => 'New York']],
        private bool $throwOnBuy = false,
    ) {
    }

    public function searchNumbers(string $country, array $options = []): array
    {
        return $this->results;
    }

    public function buyNumber(string $country, array $options = []): array
    {
        if ($this->throwOnBuy) {
            throw new OutOfStockException('fake: provisioning failed');
        }

        return [
            'provider_ref' => 'SID-'.substr(md5($options['number'] ?? uniqid()), 0, 10),
            'number' => (string) ($options['number'] ?? '+15550009999'),
            'monthly_cost' => $this->cost,
            'capabilities' => ['sms' => true, 'voice' => true],
        ];
    }

    public function sendSms(string $from, string $to, string $body): array
    {
        return ['provider_ref' => 'MSG-1', 'status' => 'queued'];
    }

    public function releaseNumber(string $providerRef): void
    {
        $this->released[] = $providerRef;
    }

    public function monthlyCost(string $country): float
    {
        return $this->cost;
    }
}
