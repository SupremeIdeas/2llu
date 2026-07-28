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

    /**
     * Place an order for a gift card and return a NORMALIZED result:
     *   ['provider_tx_id' => string, 'status' => 'delivered'|'processing'|'failed',
     *    'receipt' => ['epin'=>?, 'code'=>?, 'redemption_url'=>?, 'account_id'=>?,
     *                  'instructions'=>?, 'terms'=>?, 'expires_at'=>?, 'delivery_type'=>?]]
     * The caller has already debited the wallet; throw GiftCardProviderException
     * on failure so the caller can refund.
     *
     * @param  array<string, string>  $fields  recipient/required fields (key => value)
     * @return array<string, mixed>
     *
     * @throws GiftCardProviderException
     */
    public function order(string $providerProductId, float $amount, string $currency, array $fields, string $reference): array;
}
