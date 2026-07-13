<?php

namespace App\Services\Pricing;

use App\Models\EsimPlan;
use App\Models\PricingEngineLog;
use App\Models\Setting;

/**
 * PricingEngine — the ONE place a retail price is ever calculated
 * (blueprint Sections 1.4 & 13). No controller, job, or view may compute a
 * price; they all call this service.
 *
 * The Golden Rule: Retail = Provider Cost + NaaraSim Margin. The user always
 * pays retail; NaaraSim always pays cost. Two guards make it impossible to
 * quote below a safe floor:
 *   - Airalo minimum-selling-price guard (contractual, Airalo plans only)
 *   - MarginGuard (never at/below cost + minimum profit) — cannot be disabled
 * Both only ever correct the price UPWARD, and every calculation is logged to
 * pricing_engine_logs.
 */
class PricingEngine
{
    /**
     * Effective retail (USD) for a plan: markup formula, manual override, then
     * the Airalo and MarginGuard floors. This is the value quoted to users
     * (via final_retail_usd) and used by getProfitSummary.
     */
    public function calculateRetail(EsimPlan $plan, bool $log = true): float
    {
        $cost = (float) $plan->cost_price_usd;

        $markup = $plan->override_markup_pct !== null
            ? (float) $plan->override_markup_pct
            : (float) Setting::getValue('pricing.default_markup_pct', 30);

        // Layer 2: markup formula. A manual fixed price bypasses the formula
        // (Layer 3, priority 1) but still passes through the guards below.
        $computed = round($cost * (1 + $markup / 100), 2);
        if ($plan->manual_retail_usd !== null) {
            $computed = (float) $plan->manual_retail_usd;
        }

        $preGuard = $computed;
        $guard = 'none';

        // Airalo minimum-selling-price guard (contractual, Airalo only).
        if ($plan->provider === 'airalo'
            && $plan->airalo_min_price !== null
            && $computed < (float) $plan->airalo_min_price) {
            $computed = (float) $plan->airalo_min_price;
            $guard = 'airalo_min';
        }

        // MarginGuard: never at/below cost + minimum profit. Final safety net.
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $floor = $cost + $minProfit;
        if ($computed < $floor) {
            $computed = round($floor, 4);
            $guard = 'margin_guard';
        }

        if ($log) {
            $this->log(
                planId: $plan->id,
                provider: $plan->provider,
                cost: $cost,
                markup: $markup,
                computed: $preGuard,
                final: $computed,
                guard: $guard,
            );
        }

        return $computed;
    }

    /**
     * Retail (USD) for a per-OTP / SMS charge. Cost is fetched live from the
     * provider before quoting (never hard-coded). Per-provider markup, with a
     * per-SMS minimum-profit floor.
     */
    public function calculateSmsRetail(float $cost, string $provider): float
    {
        $markup = (float) Setting::getValue("pricing.sms_markup_pct.$provider", 40);
        $computed = round($cost * (1 + $markup / 100), 4);

        $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
        $floor = $cost + $minProfit;
        $final = max($computed, $floor);

        $this->log(
            planId: null,
            provider: $provider,
            cost: $cost,
            markup: $markup,
            computed: $computed,
            final: $final,
            guard: $final > $computed ? 'margin_guard' : 'none',
        );

        return $final;
    }

    /**
     * Cost / retail / profit breakdown for a plan (admin-only view). The cost
     * is included here for the admin profit panel and must never be surfaced
     * to end users.
     */
    public function getProfitSummary(EsimPlan $plan, bool $log = true): array
    {
        $cost = (float) $plan->cost_price_usd;
        $retail = $this->calculateRetail($plan, $log);
        $profit = round($retail - $cost, 2);

        return [
            'cost_price' => round($cost, 4),
            'retail_price' => $retail,
            'profit_usd' => $profit,
            'profit_pct' => $cost > 0 ? round($profit / $cost * 100, 1) : 0.0,
        ];
    }

    /**
     * Recompute a plan's computed_retail_usd (the engine price, ignoring any
     * manual override) and persist it. final_retail_usd then follows via the
     * generated COALESCE(manual, computed) column. Call after catalogue sync
     * and whenever the global markup changes.
     */
    public function recompute(EsimPlan $plan): EsimPlan
    {
        $original = $plan->manual_retail_usd;

        // Temporarily ignore the manual override so we store the pure engine
        // price into computed_retail_usd (the override lives in its own column).
        $plan->manual_retail_usd = null;
        $enginePrice = $this->calculateRetail($plan);
        $plan->manual_retail_usd = $original;

        $plan->computed_retail_usd = $enginePrice;
        $plan->save();

        return $plan;
    }

    /**
     * Persist one audit row per calculation. guard_delta is the upward
     * correction a guard applied (>= 0); 0 when no guard fired.
     */
    private function log(?int $planId, string $provider, float $cost, float $markup, float $computed, float $final, string $guard): void
    {
        PricingEngineLog::create([
            'plan_id' => $planId,
            'provider' => $provider,
            'cost_price' => $cost,
            'markup_used' => $markup,
            'computed_retail' => $computed,
            'final_retail' => $final,
            'guard_active' => $guard,
            'guard_delta' => round($final - $computed, 4),
        ]);
    }
}
