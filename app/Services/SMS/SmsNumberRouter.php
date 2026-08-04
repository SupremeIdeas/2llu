<?php

namespace App\Services\SMS;

use App\Exceptions\LowBalanceException;
use App\Exceptions\MaintenanceException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\SmsException;
use App\Jobs\AlertAdminJob;
use App\Models\Setting;
use App\Models\SmsOrder;
use App\Services\Pricing\PricingEngine;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Str;
use Throwable;

/**
 * SmsNumberRouter — capability routing for every number purchase
 * (blueprint Section 11). It reads the request (country + type), picks the
 * provider that OWNS that country+type, and falls back ONLY within the same
 * lane — never substituting a different country or type. If a lane is
 * exhausted it refunds and tells the user honestly.
 */
class SmsNumberRouter
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly WalletService $wallet,
    ) {}

    public function order(NumberRequest $request): SmsOrderResult
    {
        if ($request->type === NumberRequest::TYPE_PERMANENT) {
            // Permanent numbers are fully built and provisioned by
            // PermanentNumberRouter (search → provision → monthly billing); every
            // real caller (Wizard, GetNumber) uses that router directly, so this
            // branch is unreachable. It stays as a defensive guard: this SMS lane
            // handles OTP + rentals only and must never order a permanent number.
            throw new SmsException('Permanent numbers are handled by the permanent-number module, not this lane.');
        }

        try {
            return $this->attempt($request);
        } catch (SmsException $e) {
            // Never charge without delivering: refund the caller's wallet if it
            // pre-charged. The Developer API passes no `charged` here and refunds
            // its own prepaid API wallet instead (see OrderController).
            if ($request->charged !== null) {
                $this->wallet->refund($request->user, $request->charged, $request->currency, [
                    'description' => 'Number order failed — no provider available in lane',
                    'reference' => 'number-refund:'.$request->user->id.':'.now()->timestamp,
                ]);
            }

            throw $e;
        }
    }

    /**
     * The wallet-free lane attempt: pick the owning provider for (country, type),
     * fall back only WITHIN the lane, create the order on success, and throw
     * SmsException if the whole lane is exhausted — WITHOUT refunding. The CALLER
     * owns the money (user wallet for the storefront, prepaid API wallet for the
     * Developer API), so this shared loop never assumes whose money paid.
     *
     * The margin check uses `$request->charged` (the amount actually collected)
     * as the ceiling, so a provider whose live cost would eat that margin is
     * skipped — this works for BOTH the retail and the developer price.
     */
    public function attempt(NumberRequest $request): SmsOrderResult
    {
        $lane = $this->laneFor($request->country, $request->type);
        $errors = [];

        foreach ($lane as $provider) {
            try {
                /** @var SmsProviderInterface $svc */
                $svc = app("number.{$provider}");

                // "Any service" (full rent) only routes to providers that support
                // it — a rented number that receives SMS from EVERY service.
                if ($this->isFullRent($request) && ! $svc->supportsFullRent()) {
                    $errors[$provider] = 'no_full_rent';

                    continue;
                }

                $cost = $svc->priceFor($request->country, $request->service, $request->operator)
                    * $this->rentalMultiplier($request); // live; throws OutOfStock

                // The user was quoted+charged `charged` at checkout. Protect that
                // margin: if the live cost now exceeds (charged - min profit) —
                // e.g. an operator/filter price swing — skip this provider. When
                // there is no pre-charge, fall back to a fresh retail quote.
                $retail = $request->charged ?? $this->pricing->calculateSmsRetail($cost, $provider);
                $minProfit = (float) Setting::getValue('pricing.sms_min_profit', 0.01);
                // Round to the money columns' 4-dp precision so a charge sitting
                // exactly at cost+minProfit (e.g. a floor-clamped coupon price)
                // isn't rejected by float noise (0.21 - 0.01 = 0.19999…).
                $maxCost = round($retail - $minProfit, 4);
                if ($cost > $maxCost && $cost > 0) {
                    $errors[$provider] = 'cost_exceeds_margin';

                    continue;
                }

                $buyOpts = array_filter([
                    'max_price' => $maxCost,
                    'operator' => $request->operator,
                    'rental_time' => $request->rentalTime,
                    'auto_renew' => $request->autoRenew ?: null,
                ], fn ($v) => $v !== null);
                $buy = $request->type === NumberRequest::TYPE_RENTAL
                    ? $svc->buyRental($request->country, $request->service, $buyOpts)
                    : $svc->buyOtp($request->country, $request->service, $buyOpts);

                $order = SmsOrder::create([
                    'user_id' => $request->user->id,
                    'provider' => $provider,
                    'service_name' => $request->service,
                    'country' => $request->country, // for admin "by country" analytics
                    'type' => $request->type, // otp|rental — badges the number's Model
                    'getatext_id' => $buy['provider_ref'], // provider order ref (any provider)
                    'phone_number' => $buy['number'],
                    'status' => 'waiting',
                    'provider_cost' => $cost,
                    'charged_to_user' => $retail,
                    'profit' => round($retail - $cost, 4),
                    'ordered_at' => now(),
                ]);

                // Itemised receipt (BUILD-7 §1) — best-effort, never blocks the order.
                \App\Support\PurchaseReceipt::send($request->user, ucfirst($request->type).' number', $retail, 'NUM-'.$order->id);

                return SmsOrderResult::success($provider, $order, $buy, $cost, $retail);
            } catch (OutOfStockException|MaintenanceException $e) {
                $errors[$provider] = $e->getMessage(); // try next in the SAME lane
            } catch (LowBalanceException $e) {
                AlertAdminJob::dispatch(
                    code: strtoupper($provider).'_wallet_empty',
                    message: "NaaraSim's {$provider} wallet is empty — top up to resume number orders.",
                    context: ['provider' => $provider],
                );
                $errors[$provider] = 'low_balance';
            } catch (Throwable $e) {
                $errors[$provider] = $e->getMessage();
            }
        }

        // Every provider in the lane failed — alert + honest message. The refund
        // (if the caller pre-charged) is the caller's responsibility: order()
        // does it for the user wallet, OrderController for the API wallet.
        AlertAdminJob::dispatch(
            code: 'no_number_in_lane',
            message: "No provider could serve a {$request->type} number for {$request->country}.",
            context: ['request' => $request->toArray(), 'errors' => $errors],
        );

        throw new SmsException('No number available for that country right now. Try another country or check back shortly.');
    }

    /**
     * Live quote for a request WITHOUT buying: the first in-lane provider that
     * has stock, its cost, and the retail (USD) the user would pay. Used by the
     * checkout UI to debit before ordering. Throws SmsException if no provider
     * in the lane can serve it.
     *
     * @return array{provider: string, cost: float, retail: float}
     */
    public function quote(NumberRequest $request): array
    {
        foreach ($this->laneFor($request->country, $request->type) as $provider) {
            try {
                /** @var SmsProviderInterface $svc */
                $svc = app("number.{$provider}");
                if ($this->isFullRent($request) && ! $svc->supportsFullRent()) {
                    continue;
                }
                $cost = $svc->priceFor($request->country, $request->service, $request->operator)
                    * $this->rentalMultiplier($request);

                return [
                    'provider' => $provider,
                    'cost' => $cost,
                    'retail' => $this->pricing->calculateSmsRetail($cost, $provider),
                ];
            } catch (Throwable $e) {
                // try next in lane
            }
        }

        throw new SmsException('No number available for that country right now.');
    }

    /**
     * Operator comparison for the Step-3 picker (Numbers V6 §3). Merges every
     * in-lane provider's networks into one RETAIL-priced list — carrier names are
     * shown, the underlying provider is masked (rule 1.2), and provider COST is
     * never returned. Sorted cheapest-first with the best in-stock row flagged.
     *
     * @return list<array{operator:string, label:string, retail:float, available:int, success:?float, in_stock:bool, best:bool}>
     */
    public function compareOperators(string $country, string $service, string $type): array
    {
        $rows = [];
        foreach ($this->laneFor($country, $type) as $provider) {
            $svc = app("number.{$provider}");
            try {
                if (method_exists($svc, 'operators')) {
                    // Provider exposes per-network breakdown (5sim).
                    foreach ($svc->operators($country, $service) as $op) {
                        if ($op['cost'] <= 0) {
                            continue;
                        }
                        $rows[] = [
                            'operator' => (string) $op['operator'],           // raw slug — used to buy
                            'label' => $this->prettyOperator($op['operator']), // shown to the user
                            'retail' => round($this->pricing->calculateSmsRetail($op['cost'], $provider), 2),
                            'available' => (int) $op['count'],
                            'success' => $op['rate'] > 0 ? round($op['rate'], 1) : null,
                            'in_stock' => $op['count'] > 0,
                        ];
                    }
                } else {
                    // Single-route provider — one masked "Standard" row (auto).
                    $cost = $svc->priceFor($country, $service);
                    if ($cost > 0) {
                        $rows[] = [
                            'operator' => '',    // no specific network — buys "auto/best"
                            'label' => 'Standard',
                            'retail' => round($this->pricing->calculateSmsRetail($cost, $provider), 2),
                            'available' => 1,
                            'success' => null,
                            'in_stock' => true,
                        ];
                    }
                }
            } catch (Throwable) {
                // provider has nothing for this pairing — skip it
            }
        }

        // In-stock ahead of out-of-stock, then cheapest first; flag the best.
        usort($rows, fn ($a, $b) => [$b['in_stock'], $a['retail']] <=> [$a['in_stock'], $b['retail']]);
        $flagged = false;
        foreach ($rows as $i => $row) {
            $rows[$i]['best'] = ! $flagged && $row['in_stock'];
            $flagged = $flagged || $row['in_stock'];
        }

        return $rows;
    }

    /** Cheapest in-stock country for a service (Smart Buy — auto-best-country). */
    public function cheapestCountryFor(string $service): ?string
    {
        $svc = app('number.fivesim');

        return method_exists($svc, 'cheapestCountryFor') ? $svc->cheapestCountryFor($service) : null;
    }

    /** Title-case a 5sim operator slug for display (virtual21 → Virtual 21). */
    private function prettyOperator(string $op): string
    {
        $op = str_replace('_', ' ', $op);
        $op = preg_replace('/([a-z])(\d)/', '$1 $2', $op) ?? $op;

        return Str::title($op);
    }

    /**
     * Price multiplier for a chosen long-rental duration (US/Getatext). Admin-set
     * per tier so we price our own rental plans; 1 for a short-term rental or any
     * non-duration request. The buy's margin guard reconciles the live cost, so a
     * generous multiplier fails safe (skips + refunds) rather than losing money.
     */
    private function rentalMultiplier(NumberRequest $request): float
    {
        if ($request->type !== NumberRequest::TYPE_RENTAL || $request->rentalTime === null) {
            return 1.0;
        }
        $defaults = ['1w' => 1.0, '1mo' => 3.5, '3mo' => 9.0];

        return (float) Setting::getValue(
            "numbers.rental_multiplier.{$request->rentalTime}",
            $defaults[$request->rentalTime] ?? 1.0,
        );
    }

    /** A rental for "any service" — needs a full-rent-capable provider. */
    private function isFullRent(NumberRequest $request): bool
    {
        return $request->type === NumberRequest::TYPE_RENTAL
            && $request->service === NumberRequest::SERVICE_ANY;
    }

    /**
     * Ordered provider list for a request. Same-lane fallback only — a non-US
     * request never falls back to a US provider, and an OTP request never
     * becomes a permanent number (blueprint Section 11.1).
     *
     * @return array<int, string>
     */
    public function laneFor(string $country, string $type): array
    {
        $isUs = in_array(strtolower($country), ['usa', 'us'], true);

        return match ($type) {
            NumberRequest::TYPE_OTP => $isUs
                ? ['getatext', 'fivesim', 'herosms', 'virtsms']
                : ['fivesim', 'herosms', 'virtsms'],
            NumberRequest::TYPE_RENTAL => $isUs
                ? ['getatext', 'fivesim', 'herosms', 'virtsms']
                : ['fivesim', 'herosms', 'virtsms'],
            NumberRequest::TYPE_PERMANENT => ['twilio', 'telnyx'],
            default => throw new SmsException("Unknown number type [{$type}]."),
        };
    }
}
