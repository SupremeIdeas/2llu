# 2LLU — Batch 4: Geo Seeding (Country → State → LGA/District)
### Give this whole file to Claude Code as one prompt. Depends on Batch 3's geo schema.

---

## Why this isn't "one file per country" with hardcoded lists

I tried that first, honestly, and it's the wrong call. I pulled a widely-cited
"complete" LGA list for Nigeria and it had Bayelsa's LGA count wrong (8 vs 9
across two different results), Adamawa's count wrong (21 vs 22), and names
mangled — "Kastina" instead of Katsina, "Nassarawa" spelled two different
ways in the same document. That's what's actually out there when you search
for this. If I'd copied it into a seeder, you'd have silently wrong geo
data baked into KYC and matching from day one, and nobody would notice
until a real user's LGA didn't match anything.

**The fix**: seed from **geoBoundaries.org** — an open-license (ODC-ODbL),
academically maintained global database of administrative boundaries
(ADM0 = country, ADM1 = state/region, ADM2 = district/LGA/county) used by
USAID and UN OCHA humanitarian data projects. Same source, same format,
same pipeline for all 5 countries — not five different community repos of
inconsistent quality.

## Step 1 — Confirmed country list

Paystack officially supports 5 countries for merchant accounts (verified
today, not assumed): **Nigeria, Ghana, South Africa, Kenya, Côte d'Ivoire.**
Egypt and Rwanda are in private beta — don't seed those as if live; add
them when Paystack actually turns them on for merchants.

## Step 2 — Seeder pulls from geoBoundaries, doesn't hardcode

```php
// app/Console/Commands/SeedGeoBoundaries.php
class SeedGeoBoundaries extends Command
{
    protected $signature = 'geo:seed {country?}';

    const COUNTRIES = ['NGA' => 'NG', 'GHA' => 'GH', 'ZAF' => 'ZA', 'KEN' => 'KE', 'CIV' => 'CI'];
    const EXPECTED_ADM2_COUNT = ['NGA' => 774, 'GHA' => 261, 'ZAF' => 52, 'KEN' => 47, 'CIV' => 108]; // sanity check, not gospel — verify against geoBoundaries' own metadata at seed time, don't trust a number I typed from memory either

    public function handle(): void
    {
        foreach (self::COUNTRIES as $iso3 => $iso2) {
            $country = GeoCountry::updateOrCreate(['code' => $iso2], ['name' => $this->countryName($iso3)]);

            $adm1 = Http::timeout(15)->get("https://www.geoboundaries.org/api/current/gbOpen/{$iso3}/ADM1/")->json();
            $states = $this->importLevel($adm1, $country);

            $adm2 = Http::timeout(15)->get("https://www.geoboundaries.org/api/current/gbOpen/{$iso3}/ADM2/")->json();
            $localityCount = $this->importLocalities($adm2, $states);

            // Fail loudly on a mismatch instead of silently seeding a wrong count
            $expected = self::EXPECTED_ADM2_COUNT[$iso3] ?? null;
            if ($expected && abs($localityCount - $expected) > $expected * 0.1) {
                $this->error("{$iso3}: expected ~{$expected} ADM2 units, got {$localityCount} — check geoBoundaries source before trusting this seed.");
            }
        }
    }
}
```

Cache the fetched GeoJSON to `storage/app/geo-cache/` on first successful
run — this is production seed data, not something that should depend on
geoBoundaries.org being up at deploy time. Re-fetch only on `--refresh`.

**Nigeria gets one extra layer**: pair geoBoundaries' ADM2 (LGA) with
[`temikeezy/nigeria-geojson-data`](https://github.com/temikeezy/nigeria-geojson-data)
(MIT license, actively maintained, PRs welcome for corrections) for ward-level
data — 8,800+ wards with coordinates, one level finer than LGA. This is your
"town" equivalent for Nigeria specifically:
```php
$wards = Http::timeout(15)->get('https://temikeezy.github.io/nigeria-geojson-data/data/full.json')->json();
```
The other 4 countries don't have an equivalently maintained ward-level
source I could verify — seed those to ADM2 (district/county) only for now,
and treat sub-district town data as a per-country research task rather
than guessing at a source that might not be trustworthy.

## Step 3 — Street level: not seeded, resolved live

There is no reliable complete street-level dataset for any of these 5
countries, from geoBoundaries or anywhere else — streets change constantly
and aren't administrative units, so no government publishes an exhaustive
list. Trying to seed this would mean fabricating it.

Instead: at registration, once a user has picked their real country → state
→ LGA/district from the seeded data above, their street/town address is
captured through **live autocomplete** — Google Places Autocomplete,
already in your stack from the Guardians module — biased to the
state/LGA they already selected. Store whatever the API returns
(formatted address + place_id + lat/long) rather than trying to match it
against a static table. This is strictly more accurate than a static seed
could ever be, for less engineering effort.

```php
Schema::table('circle_members', function (Blueprint $table) {
    $table->string('street_address')->nullable();
    $table->string('google_place_id')->nullable();
    $table->decimal('address_lat', 10, 7)->nullable();
    $table->decimal('address_lng', 10, 7)->nullable();
});
```
No `geo_streets` table gets populated — the schema stub from Batch 3 can be
dropped or left empty; live capture replaces it.

## Step 4 — What each country actually seeds to

| Country | ADM1 (State/Region) | ADM2 (LGA/District/County) | Finer level |
|---|---|---|---|
| Nigeria | 36 states + FCT | 774 LGAs | 8,800+ wards (temikeezy dataset) |
| Ghana | 16 regions | ~261 districts | live autocomplete only |
| Kenya | 47 counties | sub-counties (geoBoundaries ADM2) | live autocomplete only |
| South Africa | 9 provinces | 52 districts/metros | live autocomplete only |
| Côte d'Ivoire | ~14 districts | ~108 departments (geoBoundaries ADM2) | live autocomplete only |

The Kenya/South Africa/Côte d'Ivoire counts above are what I'd expect from
general knowledge, not numbers I'm asking you to trust blindly — the
`EXPECTED_ADM2_COUNT` sanity check in Step 2 is exactly there to catch it
if reality doesn't match, log the discrepancy, and let you verify against
geoBoundaries' own published metadata rather than silently seeding
whatever came back.

## Done when
- All 5 countries seed successfully from geoBoundaries with the sanity-check
  passing (or a clear logged discrepancy, not a silent wrong count)
- Nigeria additionally has ward-level data from the temikeezy dataset
- No `geo_streets` data is fabricated; street capture happens live via
  Google Places Autocomplete at registration, biased to the user's already-
  selected state/LGA
- The geo fetch is cached locally so seeding doesn't depend on external
  uptime at deploy time
