<?php

namespace App\Support;

use App\Models\GiftCardProduct;
use App\Services\Pricing\PricingEngine;

/**
 * Naara Gift customer pricing. Derives the PROVIDER COST for a face value from
 * the private cost_meta, then returns the RETAIL through PricingEngine — the
 * provider's own suggested price is never shown, cost is never exposed. Display
 * calls don't log (only the authoritative purchase quote does, in Phase 3).
 */
class GiftCardPricing
{
    public function __construct(private PricingEngine $pricing) {}

    /** Retail (USD) the customer pays for a given face value. */
    public function retail(GiftCardProduct $product, float $face, bool $log = false): float
    {
        return $this->pricing->giftCardRetail($this->cost($product, $face), $product->provider, $log);
    }

    /**
     * Denomination options for the brand-detail UI, priced at retail.
     *
     * @return array{type:string, options?:array, min?:float, max?:float}
     */
    public function denominations(GiftCardProduct $product): array
    {
        if ($product->isRange()) {
            return [
                'type' => 'RANGE',
                'min' => (float) $product->min_amount,
                'max' => (float) $product->max_amount,
            ];
        }

        $options = collect((array) $product->fixed_denominations)
            ->filter(fn ($f) => is_numeric($f) && (float) $f > 0)
            ->map(fn ($f) => ['face' => (float) $f, 'retail' => $this->retail($product, (float) $f)])
            ->values()->all();

        return ['type' => 'FIXED', 'options' => $options];
    }

    /** The provider cost for a face value — PRIVATE (never returned to the client). */
    private function cost(GiftCardProduct $product, float $face): float
    {
        $meta = (array) $product->cost_meta;

        if ($product->provider === 'reloadly') {
            $discount = (float) ($meta['discountPercentage'] ?? 0);
            $fee = (float) ($meta['senderFee'] ?? 0);

            return round($face * (1 - $discount / 100) + $fee, 4);
        }

        // Zendit (failover): best-effort; default to face so MarginGuard floors
        // retail sensibly when a precise per-denomination cost isn't available.
        return round($face, 4);
    }
}
