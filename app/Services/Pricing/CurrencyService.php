<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Currency display (blueprint Section 13.4). All internal pricing is USD; NGN
 * is layered on top for display only. The rate is cached 1h and sourced either
 * manually (admin) or automatically from Airalo's exchange rates — with a safe
 * fallback so a provider hiccup never breaks price rendering.
 *
 * This only ever formats RETAIL prices. Cost is never passed here.
 */
class CurrencyService
{
    private const FALLBACK_RATE = 1500.0;

    public function getUsdToNgn(): float
    {
        return Cache::remember('usd_ngn_rate', 3600, function () {
            if (Setting::getValue('pricing.ngn_rate_source', 'auto') === 'manual') {
                return (float) Setting::getValue('pricing.manual_ngn_rate', self::FALLBACK_RATE);
            }

            try {
                $rates = app('esim.airalo')->getExchangeRates();
                $ngn = collect($rates['rates'] ?? [])->firstWhere('to', 'NGN')['mid'] ?? null;

                return $ngn ? (float) $ngn : self::FALLBACK_RATE;
            } catch (\Throwable $e) {
                return (float) Setting::getValue('pricing.manual_ngn_rate', self::FALLBACK_RATE);
            }
        });
    }

    /**
     * Format a USD retail amount for display as both USD and NGN.
     *
     * @return array{usd: string, ngn: string, usd_amount: float, ngn_amount: float}
     */
    public function displayPrice(float $usd): array
    {
        $ngnAmount = round($usd * $this->getUsdToNgn());

        return [
            'usd' => '$'.number_format($usd, 2),
            'ngn' => 'NGN '.number_format($ngnAmount, 0),
            'usd_amount' => round($usd, 2),
            'ngn_amount' => $ngnAmount,
        ];
    }
}
