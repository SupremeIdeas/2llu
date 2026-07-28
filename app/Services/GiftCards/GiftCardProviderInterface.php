<?php

namespace App\Services\GiftCards;

/**
 * Naara Gift provider contract. Reloadly (primary) and Zendit (failover) both
 * implement it, so the catalogue sync + storefront treat them as one. Phase 1
 * covers catalogue + balance; ordering/redemption is added in Phase 2.
 */
interface GiftCardProviderInterface
{
    /** 'reloadly' | 'zendit'. */
    public function key(): string;

    /** True only when the provider's keys are configured. */
    public function available(): bool;

    /**
     * The provider's full gift-card catalogue, NORMALIZED to the shared shape
     * (see GiftCardCatalogueSyncService for the persisted columns).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCatalogue(): array;

    /** Provider wallet balance (for admin oversight). */
    public function getBalance(): float;
}
