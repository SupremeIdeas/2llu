<?php

namespace App\Services\eSIM;

/**
 * Result of a successful ProviderRouter order (blueprint Section 6). Carries
 * the winning provider, its raw payload, and the money breakdown. `cost` and
 * `profit` are internal-only and must never be surfaced to end users.
 */
class EsimOrderResult
{
    public function __construct(
        public readonly string $provider,
        public readonly array $payload,
        public readonly float $cost,
        public readonly float $charged,
        public readonly float $profit,
    ) {
    }

    public static function success(string $provider, array $payload, float $cost, float $charged): self
    {
        return new self(
            provider: $provider,
            payload: $payload,
            cost: $cost,
            charged: $charged,
            profit: round($charged - $cost, 4),
        );
    }

    /** User-safe view — never includes cost or profit. */
    public function toPublicArray(): array
    {
        return [
            'provider' => $this->provider,
            'payload' => $this->payload,
        ];
    }
}
