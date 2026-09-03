<?php

namespace App\Support;

use App\Models\EsimCountryImage;
use App\Models\EsimPlan;
use App\Models\EsimRegionImage;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed navigation data for the customer eSIM browser (BUILD-8 §3).
 *
 * Everything here reads ONLY from the already-synced esim_plans table and the
 * two admin image tables — never a live provider call (§3.5.3) — and is cached
 * like NumberCatalogue (Cache::rememberForever, busted on the next sync or on an
 * admin image change). The grid stores raw USD teaser amounts + counts; the view
 * formats them into the viewer's currency per-request, so a currency change never
 * needs a re-query.
 *
 * "Local", "Regional" and "Global" mirror EsimPlan::coverage_type. A plan whose
 * coverage_type is still null (a row synced before BUILD-8, not yet re-synced) is
 * inferred here from its country count so the grid is never empty in the gap.
 */
class EsimCatalogue
{
    private const CACHE = 'esim.nav.grid.v1:'; // + data|full

    private const IMG_COUNTRY = 'esim.nav.country_images.v1';

    private const IMG_REGION = 'esim.nav.region_images.v1';

    /** Regional plans a provider left unlabelled land under this fallback tile. */
    public const REGION_OTHER = 'other';

    /**
     * The navigation grid for one line (data-only vs Full eSIM):
     *   [
     *     'local'   => [ ['code'=>'NG','name'=>'Nigeria','from_usd'=>3.5,'count'=>4,'icon'=>?url], ... ],
     *     'regions' => [ ['slug'=>'europe','label'=>'Europe','from_usd'=>9.0,'count'=>7,'icon'=>?url], ... ],
     *     'global'  => ['from_usd'=>19.0,'count'=>2,'icon'=>?url] | null,
     *   ]
     *
     * @return array{local: list<array<string,mixed>>, regions: list<array<string,mixed>>, global: ?array<string,mixed>}
     */
    public static function grid(bool $hasVoice): array
    {
        $raw = Cache::rememberForever(self::CACHE.($hasVoice ? 'full' : 'data'),
            fn () => self::buildGrid($hasVoice));

        // Decorate with admin images at read time (images cached separately so an
        // image upload busts only the small image maps, not the whole grid).
        $countryImages = self::countryIconMap();
        $regionImages = self::regionIconMap();

        $raw['local'] = array_map(function ($row) use ($countryImages) {
            $row['icon'] = $countryImages[$row['code']] ?? null;

            return $row;
        }, $raw['local']);

        $raw['regions'] = array_map(function ($row) use ($regionImages) {
            $row['icon'] = $regionImages[$row['slug']] ?? null;

            return $row;
        }, $raw['regions']);

        if ($raw['global']) {
            $raw['global']['icon'] = $regionImages[EsimRegions::WORLD] ?? null;
        }

        return $raw;
    }

    /**
     * @return array{local: list<array<string,mixed>>, regions: list<array<string,mixed>>, global: ?array<string,mixed>}
     */
    private static function buildGrid(bool $hasVoice): array
    {
        $local = [];   // iso => ['count'=>int,'from'=>float]
        $regions = []; // slug => ['count'=>int,'from'=>float]
        $global = ['count' => 0, 'from' => null];

        EsimPlan::query()
            ->where('is_active', true)
            ->where('has_voice', $hasVoice)
            ->get(['coverage_type', 'region_slug', 'countries', 'final_retail_usd'])
            ->each(function (EsimPlan $p) use (&$local, &$regions, &$global) {
                $price = (float) $p->final_retail_usd;
                $isos = collect((array) $p->countries)
                    ->map(fn ($c) => strtoupper((string) $c))
                    ->filter(fn ($c) => strlen($c) === 2)
                    ->values();

                $coverage = $p->coverage_type
                    ?? ($isos->count() <= 1 ? EsimPlan::COVERAGE_LOCAL : EsimPlan::COVERAGE_REGIONAL);

                if ($coverage === EsimPlan::COVERAGE_GLOBAL) {
                    $global['count']++;
                    $global['from'] = self::min($global['from'], $price);

                    return;
                }

                if ($coverage === EsimPlan::COVERAGE_REGIONAL) {
                    $slug = $p->region_slug ?: self::REGION_OTHER;
                    $regions[$slug] ??= ['count' => 0, 'from' => null];
                    $regions[$slug]['count']++;
                    $regions[$slug]['from'] = self::min($regions[$slug]['from'], $price);

                    return;
                }

                // Local: tally every ISO the plan reaches (usually exactly one).
                foreach ($isos as $iso) {
                    $local[$iso] ??= ['count' => 0, 'from' => null];
                    $local[$iso]['count']++;
                    $local[$iso]['from'] = self::min($local[$iso]['from'], $price);
                }
            });

        // Shape + sort.
        $localRows = [];
        foreach ($local as $iso => $agg) {
            $localRows[] = ['code' => $iso, 'name' => CountryNames::name($iso), 'from_usd' => $agg['from'], 'count' => $agg['count']];
        }
        usort($localRows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $regionRows = [];
        foreach ($regions as $slug => $agg) {
            $regionRows[] = [
                'slug' => $slug,
                'label' => $slug === self::REGION_OTHER ? 'Other regions' : EsimRegions::label($slug),
                'from_usd' => $agg['from'],
                'count' => $agg['count'],
            ];
        }
        // Canonical region order first (world excluded — it's the Global tab), then any extras/other.
        $order = array_flip(EsimRegions::slugs());
        usort($regionRows, fn ($a, $b) => ($order[$a['slug']] ?? 999) <=> ($order[$b['slug']] ?? 999) ?: strcmp($a['label'], $b['label']));

        return [
            'local' => $localRows,
            'regions' => $regionRows,
            'global' => $global['count'] > 0 ? ['from_usd' => $global['from'], 'count' => $global['count']] : null,
        ];
    }

    /** Cheapest of two nullable prices. */
    private static function min(?float $a, float $b): float
    {
        return $a === null ? $b : min($a, $b);
    }

    /** @return array<string,?string> ISO2(upper) => icon_path url */
    private static function countryIconMap(): array
    {
        return Cache::rememberForever(self::IMG_COUNTRY, fn () => EsimCountryImage::query()
            ->pluck('icon_path', 'country_code')
            ->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => $v])
            ->all());
    }

    /** @return array<string,?string> region_slug => icon_path url */
    private static function regionIconMap(): array
    {
        return Cache::rememberForever(self::IMG_REGION, fn () => EsimRegionImage::query()
            ->pluck('icon_path', 'region_slug')->all());
    }

    /** The banner (detail) image for a country, or null (clean fallback). */
    public static function countryBanner(string $iso): ?string
    {
        return EsimCountryImage::where('country_code', strtoupper($iso))->value('detail_image_path');
    }

    /** The banner (detail) image for a region/global slug, or null. */
    public static function regionBanner(string $slug): ?string
    {
        return EsimRegionImage::where('region_slug', $slug)->value('detail_image_path');
    }

    /**
     * "Popular Destinations" — a photo-card row of countries that have at least
     * one admin-featured (is_featured) active local plan. This reuses the exact
     * "popular" signal the Popular tab's plan list already keys off (BUILD-8) —
     * no new admin curation surface — and the same admin-editable per-country
     * image (EsimCountryImage.detail_image_path) every other eSIM surface uses.
     * Countries rank by featured-plan count (most-covered first), tie-broken by
     * name. Cheapest featured plan on each country supplies the teaser price +
     * data/validity line.
     *
     * @return list<array{code:string,name:string,from_usd:?float,data_mb:?int,validity_days:?int,count:int,photo:?string}>
     */
    public static function popularDestinations(bool $hasVoice, int $limit = 12): array
    {
        $rows = Cache::rememberForever(self::CACHE.'popular:'.($hasVoice ? 'full' : 'data'),
            fn () => self::buildPopularDestinations($hasVoice));

        $photos = self::countryPhotoMap();

        return array_slice(array_map(function ($row) use ($photos) {
            $row['photo'] = $photos[$row['code']] ?? null;

            return $row;
        }, $rows), 0, $limit);
    }

    /** @return list<array<string,mixed>> unsliced, undecorated (no photo yet) */
    private static function buildPopularDestinations(bool $hasVoice): array
    {
        $byCountry = []; // iso => ['count'=>int,'from'=>float,'data_mb'=>?int,'validity_days'=>?int]

        EsimPlan::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->where('has_voice', $hasVoice)
            ->where(function ($q) {
                $q->where('coverage_type', EsimPlan::COVERAGE_LOCAL)->orWhereNull('coverage_type');
            })
            ->get(['countries', 'final_retail_usd', 'data_mb', 'validity_days'])
            ->each(function (EsimPlan $p) use (&$byCountry) {
                $isos = collect((array) $p->countries)
                    ->map(fn ($c) => strtoupper((string) $c))
                    ->filter(fn ($c) => strlen($c) === 2)
                    ->values();

                // A plan reaching more than one country isn't a single-destination
                // card here — the Regions tab already covers multi-country plans.
                if ($isos->count() !== 1) {
                    return;
                }
                $iso = $isos->first();
                $price = (float) $p->final_retail_usd;

                $byCountry[$iso] ??= ['count' => 0, 'from' => null, 'data_mb' => null, 'validity_days' => null];
                $byCountry[$iso]['count']++;
                if ($byCountry[$iso]['from'] === null || $price < $byCountry[$iso]['from']) {
                    $byCountry[$iso]['from'] = $price;
                    $byCountry[$iso]['data_mb'] = $p->data_mb;
                    $byCountry[$iso]['validity_days'] = $p->validity_days;
                }
            });

        $rows = [];
        foreach ($byCountry as $iso => $agg) {
            $rows[] = [
                'code' => $iso,
                'name' => CountryNames::name($iso),
                'from_usd' => $agg['from'],
                'data_mb' => $agg['data_mb'],
                'validity_days' => $agg['validity_days'],
                'count' => $agg['count'],
            ];
        }
        usort($rows, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /** @return array<string,?string> ISO2(upper) => detail_image_path url */
    private static function countryPhotoMap(): array
    {
        return Cache::rememberForever(self::IMG_COUNTRY.'.photo', fn () => EsimCountryImage::query()
            ->pluck('detail_image_path', 'country_code')
            ->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => $v])
            ->all());
    }

    /** Bust every navigation cache (call after a sync or an admin image change). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE.'data');
        Cache::forget(self::CACHE.'full');
        Cache::forget(self::CACHE.'popular:data');
        Cache::forget(self::CACHE.'popular:full');
        Cache::forget(self::IMG_COUNTRY);
        Cache::forget(self::IMG_COUNTRY.'.photo');
        Cache::forget(self::IMG_REGION);
    }
}
