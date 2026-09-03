<?php

namespace Tests\Support;

use App\Services\GiftCards\GiftCardProviderException;
use App\Services\GiftCards\GiftCardProviderInterface;

/**
 * Configurable Naara Gift provider double. Counts order() calls (to prove the
 * double-submit guard orders exactly once) and can be told to deliver, return a
 * non-throwing 'failed'/'processing' status, or throw — so the money-path guards
 * (refund-on-failure, no double charge) are exercised deterministically.
 */
class FakeGiftCardProvider implements GiftCardProviderInterface
{
    public int $orderCalls = 0;

    public function __construct(
        private string $status = 'delivered',
        private array $receipt = ['code' => 'GIFT-1234', 'epin' => 'PIN-9'],
        private bool $shouldThrow = false,
    ) {}

    public function key(): string
    {
        return 'reloadly';
    }

    public function available(): bool
    {
        return true;
    }

    public function getCatalogue(): array
    {
        return [];
    }

    public function getBalance(): float
    {
        return 0.0;
    }

    public function preflight(): array
    {
        return ['ok' => true, 'balance' => 100.0, 'currency' => 'USD', 'products' => 5, 'error' => null];
    }

    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array
    {
        $this->orderCalls++;
        if ($this->shouldThrow) {
            throw new GiftCardProviderException('provider down');
        }

        return ['provider_tx_id' => 'tx-'.$reference, 'status' => $this->status, 'receipt' => $this->receipt];
    }
}
