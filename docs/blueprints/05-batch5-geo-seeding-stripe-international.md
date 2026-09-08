# 2LLU — Batch 5: Geo Seeding for Stripe-Supported International Countries
### Extends Batch 4's `geo:seed` command — same pipeline, more countries. Give this whole file to Claude Code as one prompt.

---

## Confirmed list (pulled live from stripe.com/global, not a secondary source)

**44 fully-supported countries** where Stripe allows direct account
registration:

Australia, Austria, Belgium, Brazil, Bulgaria, Canada, Croatia, Cyprus,
Czech Republic, Denmark, Estonia, Finland, France, Germany, Gibraltar,
Greece, Hong Kong, Hungary, Ireland, Italy, Japan, Latvia, Liechtenstein,
Lithuania, Luxembourg, Malaysia, Malta, Mexico, Netherlands, New Zealand,
Norway, Poland, Portugal, Romania, Singapore, Slovakia, Slovenia, Spain,
Sweden, Switzerland, Thailand, United Arab Emirates, United Kingdom,
United States.

**Not seeded as live**: India and Indonesia are listed as "Preview" on
Stripe's own page — technically supported but not general-access yet.
Don't seed these as active until Stripe actually turns on standard
registration for them; check back before launch, not now.

**Already handled**: Côte d'Ivoire, Ghana, Kenya, Nigeria, South Africa
show on Stripe's own page as "Extended network" — that's the same 5
Paystack countries from Batch 4. Don't seed them twice.

## Step 1 — Extend Batch 4's command, don't duplicate it

Same `SeedGeoBoundaries` command, same geoBoundaries.org pipeline, same
caching and sanity-check discipline — just a longer country list. One
seeding pipeline for all 49 live-supported countries (5 Paystack + 44
Stripe) is easier to maintain than two.

```php
// app/Console/Commands/SeedGeoBoundaries.php — COUNTRIES constant extended
const COUNTRIES = [
    // Paystack (Batch 4)
    'NGA' => 'NG', 'GHA' => 'GH', 'ZAF' => 'ZA', 'KEN' => 'KE', 'CIV' => 'CI',
    // Stripe international (Batch 5)
    'AUS'=>'AU','AUT'=>'AT','BEL'=>'BE','BRA'=>'BR','BGR'=>'BG','CAN'=>'CA',
    'HRV'=>'HR','CYP'=>'CY','CZE'=>'CZ','DNK'=>'DK','EST'=>'EE','FIN'=>'FI',
    'FRA'=>'FR','DEU'=>'DE','GIB'=>'GI','GRC'=>'GR','HKG'=>'HK','HUN'=>'HU',
    'IRL'=>'IE','ITA'=>'IT','JPN'=>'JP','LVA'=>'LV','LIE'=>'LI','LTU'=>'LT',
    'LUX'=>'LU','MYS'=>'MY','MLT'=>'MT','MEX'=>'MX','NLD'=>'NL','NZL'=>'NZ',
    'NOR'=>'NO','POL'=>'PL','PRT'=>'PT','ROU'=>'RO','SGP'=>'SG','SVK'=>'SK',
    'SVN'=>'SI','ESP'=>'ES','SWE'=>'SE','CHE'=>'CH','THA'=>'TH','ARE'=>'AE',
    'GBR'=>'GB','USA'=>'US',
];
```

**Handle small/city-state territories gracefully**: Gibraltar, Hong Kong,
Liechtenstein, Malta, Singapore have little or no meaningful ADM1
subdivision — geoBoundaries may return an empty or single-item ADM1 result
for these. The seeder should fall back to treating the country itself as
its own single "state" row rather than erroring out:
```php
if (empty($adm1)) {
    GeoState::create(['country_id' => $country->id, 'name' => $country->name]);
}
```

## Step 2 — Seeding depth for international matches the diaspora design, not Nigeria's

Batch 4 went to LGA level for Nigeria because that's your flagship market
and the matching engine leans on it. For the 44 Stripe countries, ADM1
(state/province/region) is the right depth — matching for a diaspora
"home_country" contributor (from the earlier diaspora batch) only ever
needs country + state resolution, not county-level precision. If a
specific market — UK, US, Canada — ends up needing tighter matching later
because of density, that's a targeted follow-up for that one country, not
something to build speculatively into all 44 now.

Street/city capture stays exactly as designed in Batch 4: live Google
Places Autocomplete at registration, biased to the selected country/state
— no static seed, same reasoning as before (no reliable exhaustive street
dataset exists for any of these countries either).

## Step 3 — Currency alignment

These 44 countries route through the existing `StripeGateway` (already
built, per Batch 4 of the earlier diaspora work) and default to USD/GBP/EUR
per your original instruction — `circle_plans.currency` and the FX sync
job from the wallet/debt batch already handle this; no new currency logic
needed here, just confirming the geo data lines up with what's already wired.

## Done when
- `geo:seed` successfully seeds all 49 live-supported countries (5 Paystack
  + 44 Stripe) from geoBoundaries, with the sanity-check catching any
  country where the fetched ADM1 count looks obviously wrong
- Small territories (Gibraltar, Hong Kong, Liechtenstein, Malta, Singapore)
  seed cleanly without erroring on sparse ADM1 data
- India and Indonesia are explicitly excluded until Stripe moves them out
  of Preview — not silently seeded as if live
- Diaspora matching resolves correctly at country/state level for all 44
  without needing district-level data
