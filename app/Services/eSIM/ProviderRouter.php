<?php

namespace App\Services\eSIM;

use App\Exceptions\EsimProviderException;
use App\Jobs\AlertAdminJob;
use App\Models\EsimPlan;
use App\Models\OrderLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProviderRouter — profit-aware eSIM failover (blueprint Section 6).
 *
 * Tries providers in order (eSIM Go → Airalo → Quibity). The governing rule:
 * a fallback that destroys margin is worse than a failed order. So it SKIPS
 * any provider whose equivalent-plan cost would leave less than the minimum
 * profit versus what the user already paid, and if none can fulfil profitably
 * it refunds the wallet and alerts — never fulfils below cost + min profit.
 *
 * Assumes the user's wallet has already been debited `final_retail_usd`
 * (checkout debits first, then calls this). On total failure it refunds.
 */
class ProviderRouter
{
    /** @var list<string> */
    protected array $chain = ['esimgo', 'airalo', 'quibity'];

    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function orderPlan(string $naaraPlanId, User $user, string $currency = 'USD'): EsimOrderResult
    {
        $plan = EsimPlan::findOrFail($naaraPlanId);
        $charged = (float) $plan->final_retail_usd;
        $minProfit = (float) Setting::getValue('pricing.minimum_profit_usd', 0.50);
        $errors = [];

        foreach ($this->chain as $provider) {
            $pp = $this->findEquivalentPlan($plan, $provider);
            if ($pp === null) {
                continue; // provider has no equivalent plan
            }

            $cost = (float) $pp->cost_price_usd;
            if ($charged < $cost + $minProfit) {
                // Margin guard: fulfilling here would eat the margin. Skip.
                Log::warning("ProviderRouter: skipping {$provider} for plan {$naaraPlanId} — cost {$cost} too close to charged {$charged}.");
                $errors[$provider] = 'skipped: unprofitable';
                continue;
            }

            try {
                $result = app("esim.{$provider}")->orderBundle($pp->provider_plan_id);

                OrderLog::create([
                    'user_id' => $user->id,
                    'naarasim_plan_id' => $plan->id,
                    'provider' => $provider,
                    'provider_cost' => $cost,
                    'charged_to_user' => $charged,
                    'profit' => round($charged - $cost, 4),
                    'profit_pct' => $cost > 0 ? round(($charged - $cost) / $cost * 100, 3) : null,
                    'result' => 'success',
                ]);

                return EsimOrderResult::success($provider, $result, $cost, $charged);
            } catch (Throwable $e) {
                $errors[$provider] = $e->getMessage();
            }
        }

        // No provider fulfilled profitably — refund + alert (never charge
        // without delivering).
        $this->wallet->refund($user, $charged, $currency, [
            'description' => 'eSIM order failed — all providers unavailable or unprofitable',
            'reference' => "esim-refund:{$plan->id}:{$user->id}:".now()->timestamp,
        ]);

        AlertAdminJob::dispatch(
            code: 'all_esim_providers_failed',
            message: "No eSIM provider could fulfil plan {$plan->id} for user {$user->id}; wallet refunded {$charged} {$currency}.",
            context: [
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'charged' => $charged,
                'currency' => $currency,
                'errors' => $errors,
            ],
        );

        throw new EsimProviderException('Order could not be fulfilled. Wallet refunded.');
    }

    /**
     * Find the cheapest active plan from $provider that covers at least the
     * same countries, data, and validity as $plan — so a fallback never
     * downgrades what the user paid for. Cheapest cost first.
     */
    public function findEquivalentPlan(EsimPlan $plan, string $provider): ?EsimPlan
    {
        $candidates = EsimPlan::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->orderBy('cost_price_usd')
            ->get();

        foreach ($candidates as $candidate) {
            if (! $this->dataCovers($plan->data_mb, $candidate->data_mb)) {
                continue;
            }
            if (! $this->validityCovers($plan->validity_days, $candidate->validity_days)) {
                continue;
            }
            if (! $this->countriesCover($plan->countries, $candidate->countries)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /** null data_mb means unlimited. Unlimited is only covered by unlimited. */
    private function dataCovers(?int $need, ?int $have): bool
    {
        if ($need === null) {
            return $have === null;
        }

        return $have === null || $have >= $need;
    }

    private function validityCovers(?int $need, ?int $have): bool
    {
        if ($need === null) {
            return true;
        }

        return $have === null || $have >= $need;
    }

    /**
     * @param  array<int, string>|null  $need
     * @param  array<int, string>|null  $have
     */
    private function countriesCover(?array $need, ?array $have): bool
    {
        $need = $need ?? [];
        if ($need === []) {
            return true;
        }

        return empty(array_diff($need, $have ?? []));
    }
}
