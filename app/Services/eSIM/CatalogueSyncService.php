<?php

namespace App\Services\eSIM;

use App\Models\EsimPlan;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Arr;

/**
 * Syncs each provider's catalogue into esim_plans and recomputes retail
 * through the PricingEngine (blueprint Sections 5.2-5.4 & 5.3.3).
 *
 * The money-critical rules, per provider:
 *   - eSIM Go / Quibity: the catalogue `price` is the WHOLESALE cost.
 *   - Airalo: `net_price` is the cost; `minimum_selling_price` is the
 *     contractual floor (stored in airalo_min_price). NEVER store Airalo's
 *     own `price` as our retail.
 * cost_price_usd is always PRIVATE.
 */
class CatalogueSyncService
{
    public function __construct(private readonly PricingEngine $pricing)
    {
    }

    /** Sync one provider; returns the number of plans upserted. */
    public function sync(string $provider): int
    {
        $rows = match ($provider) {
            'esimgo' => $this->mapEsimGo(app('esim.esimgo')->getCatalogue()),
            'airalo' => $this->mapAiralo(app('esim.airalo')->getCatalogue()),
            'quibity' => $this->mapQuibity(app('esim.quibity')->getCatalogue()),
            default => throw new \InvalidArgumentException("Unknown eSIM provider [$provider]."),
        };

        foreach ($rows as $row) {
            $plan = EsimPlan::updateOrCreate(
                ['provider' => $provider, 'provider_plan_id' => $row['provider_plan_id']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'] ?? null,
                    'data_mb' => $row['data_mb'] ?? null,
                    'validity_days' => $row['validity_days'] ?? null,
                    'countries' => $row['countries'] ?? [],
                    'cost_price_usd' => $row['cost_price_usd'],           // PRIVATE
                    'airalo_min_price' => $row['airalo_min_price'] ?? null, // Airalo only
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );

            // Recompute retail through the single pricing owner (Part 13).
            $this->pricing->recompute($plan);
        }

        return count($rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function mapEsimGo(array $raw): array
    {
        $bundles = $raw['bundles'] ?? $raw;

        return collect($bundles)->map(fn ($b) => [
            'provider_plan_id' => $b['name'] ?? $b['bundle_name'] ?? null,
            'name' => $b['description'] ?? $b['name'] ?? 'eSIM bundle',
            'type' => $b['type'] ?? null,
            'data_mb' => $this->intOrNull($b['dataAmount'] ?? $b['data'] ?? null),
            'validity_days' => $this->intOrNull($b['duration'] ?? null),
            'countries' => $this->isoList($b['countries'] ?? []),
            'cost_price_usd' => (float) ($b['price'] ?? 0),
        ])->filter(fn ($r) => $r['provider_plan_id'] !== null)->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function mapAiralo(array $data): array
    {
        $rows = [];

        foreach ($data as $group) {
            foreach (Arr::get($group, 'operators', []) as $operator) {
                $countries = collect(Arr::get($operator, 'countries', []))
                    ->pluck('country_code')->filter()->values()->all();

                foreach (Arr::get($operator, 'packages', []) as $pkg) {
                    if (! isset($pkg['id'])) {
                        continue;
                    }
                    $rows[] = [
                        'provider_plan_id' => (string) $pkg['id'],
                        'name' => $pkg['title'] ?? $pkg['id'],
                        'type' => $pkg['type'] ?? 'sim',
                        'data_mb' => $this->intOrNull($pkg['amount'] ?? null),
                        'validity_days' => $this->intOrNull($pkg['day'] ?? null),
                        'countries' => $countries,
                        // net_price is our cost; minimum_selling_price is the floor.
                        'cost_price_usd' => (float) ($pkg['net_price'] ?? 0),
                        'airalo_min_price' => isset($pkg['minimum_selling_price'])
                            ? (float) $pkg['minimum_selling_price']
                            : null,
                    ];
                }
            }
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function mapQuibity(array $raw): array
    {
        $plans = $raw['data'] ?? $raw['plans'] ?? $raw;

        return collect($plans)->map(fn ($p) => [
            'provider_plan_id' => isset($p['id']) ? (string) $p['id'] : null,
            'name' => $p['name'] ?? 'eSIM plan',
            'type' => $p['type'] ?? null,
            'data_mb' => $this->intOrNull($p['data_mb'] ?? null),
            'validity_days' => $this->intOrNull($p['validity_days'] ?? null),
            'countries' => $this->isoList($p['countries'] ?? []),
            'cost_price_usd' => (float) ($p['price'] ?? 0),
        ])->filter(fn ($r) => $r['provider_plan_id'] !== null)->values()->all();
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Normalise a provider's countries field (list of ISO strings, or list of
     * objects with iso/code/name) to a flat list of codes.
     *
     * @return array<int, string>
     */
    private function isoList(mixed $countries): array
    {
        return collect($countries)->map(function ($c) {
            if (is_string($c)) {
                return $c;
            }

            return $c['iso'] ?? $c['code'] ?? $c['country_code'] ?? $c['name'] ?? null;
        })->filter()->values()->all();
    }
}
