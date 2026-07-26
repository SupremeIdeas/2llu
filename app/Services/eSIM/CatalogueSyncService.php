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

    /** Sync one provider; returns the number of plans upserted. Records the
     *  outcome (success/fail + count/error) for admin observability either way. */
    public function sync(string $provider): int
    {
        try {
            $count = $this->doSync($provider);
            \App\Support\SyncStatus::record($provider, ok: true, count: $count);

            return $count;
        } catch (\Throwable $e) {
            \App\Support\SyncStatus::record($provider, ok: false, error: $e->getMessage());
            \Illuminate\Support\Facades\Log::warning("[esim] Catalogue sync failed for {$provider}: ".$e->getMessage());
            throw $e;
        }
    }

    private function doSync(string $provider): int
    {
        $rows = match ($provider) {
            'esimgo' => $this->mapEsimGo(app('esim.esimgo')->getCatalogue()),
            'airalo' => $this->mapAiralo(app('esim.airalo')->getCatalogue()),
            'quibity' => $this->mapQuibity(app('esim.quibity')->getCatalogue()),
            'zendit' => $this->mapZendit(app('esim.zendit')->getCatalogue()),
            default => throw new \InvalidArgumentException("Unknown eSIM provider [$provider]."),
        };

        foreach ($rows as $row) {
            $plan = EsimPlan::updateOrCreate(
                ['provider' => $provider, 'provider_plan_id' => $row['provider_plan_id']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'] ?? null,
                    'has_voice' => $row['has_voice'] ?? false, // Naara Connect (Zendit) only
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

    /**
     * Zendit → the Naara Connect line (Full eSIMs: calls + data). We ingest ONLY
     * voice-capable offers: Zendit's data-only bundles duplicate the data trio
     * (eSIM Go / Airalo / Quibity) and would flood the Data tab, so they're
     * skipped — Zendit is deliberately the voice lane. Every ingested plan is
     * has_voice = true. Cost is `cost.fixed / currencyDivisor` — the WHOLESALE
     * price (PRIVATE); Zendit's suggested `price` block is ignored.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapZendit(array $raw): array
    {
        $offers = $raw['list'] ?? $raw['data'] ?? $raw;

        return collect($offers)
            ->filter(fn ($o) => ($o['enabled'] ?? true) && isset($o['offerId'])
                && ((bool) ($o['voiceUnlimited'] ?? false) || (int) ($o['voiceMinutes'] ?? 0) > 0))
            ->map(function ($o) {
                $cost = $o['cost'] ?? [];
                $divisor = (int) ($cost['currencyDivisor'] ?? 1) ?: 1;

                $unlimitedData = (bool) ($o['dataUnlimited'] ?? false);
                $dataGb = (float) ($o['dataGB'] ?? 0);

                return [
                    'provider_plan_id' => (string) $o['offerId'],
                    'name' => $this->zenditName($o),
                    'type' => 'Voice + Data',
                    'has_voice' => true,
                    // dataGB is in GB; store MB. Unlimited => null (matches the model).
                    'data_mb' => $unlimitedData ? null : ($dataGb > 0 ? (int) round($dataGb * 1024) : null),
                    'validity_days' => $this->intOrNull($o['durationDays'] ?? null),
                    'countries' => $this->isoList(array_merge(
                        array_filter([$o['country'] ?? null]),
                        $o['regions'] ?? [],
                    )),
                    'cost_price_usd' => (float) ($cost['fixed'] ?? 0) / $divisor,
                ];
            })
            ->filter(fn ($r) => $r['provider_plan_id'] !== '')
            ->values()
            ->all();
    }

    /** A human name for a Zendit offer: "<brand> — <data> + <voice>". */
    private function zenditName(array $o): string
    {
        $brand = $o['brandName'] ?? $o['brand'] ?? 'eSIM';

        $data = ($o['dataUnlimited'] ?? false)
            ? 'Unlimited data'
            : (($gb = (float) ($o['dataGB'] ?? 0)) > 0 ? rtrim(rtrim(number_format($gb, 1), '0'), '.').'GB' : 'Data');

        $parts = [$data];
        if ($o['voiceUnlimited'] ?? false) {
            $parts[] = 'unlimited mins';
        } elseif (($min = (int) ($o['voiceMinutes'] ?? 0)) > 0) {
            $parts[] = $min.' mins';
        }
        if (($o['smsUnlimited'] ?? false)) {
            $parts[] = 'unlimited SMS';
        } elseif (($sms = (int) ($o['smsNumber'] ?? 0)) > 0) {
            $parts[] = $sms.' SMS';
        }

        return trim($brand).' — '.implode(' + ', $parts);
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
