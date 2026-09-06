<?php

namespace Database\Seeders;

use App\Models\ThemePreset;
use Illuminate\Database\Seeder;

/**
 * NAARA THEME SYSTEM — Batch 2 §1, palette refresh + expansion (2026-09-04).
 * Seeds all 20 switchable presets.
 *
 * `naara-official` (row 1) is the permanent built-in: its tokens are TRANSCRIBED
 * from the live app.css `--brand-*` set and it emits NO override CSS (the built-in
 * look already ships), so it is re-seeded authoritatively every run. It is
 * NEVER touched by the refresh below.
 *
 * Rows 2–15 are the ORIGINAL 14 persona slugs, RECOLOURED (owner request: too
 * many of the original palettes read as teal/gold variations of naara-official
 * itself). Each new palette is inspired by the colour-story of one of 15
 * reference mockups the owner forwarded — never their copy/imagery/branding,
 * just the mood of the colours. Rows 16–20 are 5 brand-new personas, added so
 * the platform now ships 20 themes total (see newPresets() below); one is
 * inspired by the one reference image left over after the 14 recolours, the
 * rest are original combinations chosen to stay visually distinct from every
 * other preset. Every palette keeps `primary` dark enough for white button
 * text, matching the existing set's own bar (not stricter, not looser).
 * All of it is seeded with `firstOrCreate` (rows 2–20) / `updateOrCreate`
 * (row 1 only) so a re-run NEVER clobbers an admin's own tuning made through
 * the picker — a one-time migration (not this seeder) is what actually
 * updates the 14 recoloured rows in a database that already seeded the old
 * palette; see `2026_09_04_120000_refresh_theme_preset_palettes.php`.
 *
 * Icons: only `naara-official` keeps the 3D set; every other preset points at the
 * shared `naara-sprite-01` family (the sprite sheet itself ships in Batch 3 §5 —
 * until then icons render via the existing sprite, the flag is just stored data).
 * Structural `layout_variants` stay on `variant-a` here; Batch 2 §2 assigns
 * variant-b/variant-c once those partials exist, so nothing renders half-wired.
 */
class ThemePresetSeeder extends Seeder
{
    /**
     * 5 brand-new personas (rows 16–20), added alongside the palette refresh
     * above so the platform now ships 20 themes total. Each gets its own hero
     * image reused from the existing 8-image set (see
     * docs/build-specs/THEME-PLACEHOLDER-ASSETS.md) and lives on the
     * structural baseline (variant-a everywhere) since no dedicated partials
     * exist for them yet.
     */
    private function newPresets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'verdant-pulse', 'name' => 'Verdant Pulse', 'sort_order' => 16,
                'persona' => 'Fresh spring-green, crisp white cards, quick-action urban utility feel.',
                'colors' => ['primary' => '22 140 72', 'primary_dark' => '15 105 54', 'accent' => '163 230 53', 'accent_dark' => '90 127 29', 'navy' => '10 26 16', 'action' => '230 60 45'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-female'],

            ['slug' => 'cobalt-frost', 'name' => 'Cobalt Frost', 'sort_order' => 17,
                'persona' => 'Icy cobalt blue with a sky-blue pop, crisp winter-clean minimalism.',
                'colors' => ['primary' => '30 86 160', 'primary_dark' => '20 60 112', 'accent' => '56 189 248', 'accent_dark' => '36 123 161', 'navy' => '10 20 35', 'action' => '230 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'portal-gateway'],

            ['slug' => 'mango-burst', 'name' => 'Mango Burst', 'sort_order' => 18,
                'persona' => 'Tropical burnt-orange with a deep plum pop, playful travel energy.',
                'colors' => ['primary' => '198 90 10', 'primary_dark' => '150 68 8', 'accent' => '124 58 140', 'accent_dark' => '124 58 140', 'navy' => '28 14 8', 'action' => '220 70 45'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5'],
                'hero' => 'islands-male'],

            ['slug' => 'arctic-teal', 'name' => 'Arctic Teal', 'sort_order' => 19,
                'persona' => 'Cool, calm teal with a slate-blue accent — a quieter, icier cousin of the house teal.',
                'colors' => ['primary' => '20 110 120', 'primary_dark' => '14 80 88', 'accent' => '148 163 184', 'accent_dark' => '104 114 129', 'navy' => '15 20 24', 'action' => '225 65 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                'hero' => 'worldwide'],

            ['slug' => 'rosewood-luxe', 'name' => 'Rosewood Luxe', 'sort_order' => 20,
                'persona' => 'Rosewood-pink with a champagne-gold pop — upscale, boutique, gift-shop warmth.',
                'colors' => ['primary' => '122 36 54', 'primary_dark' => '90 26 40', 'accent' => '230 196 140', 'accent_dark' => '127 108 77', 'navy' => '24 12 16', 'action' => '220 65 48'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4'],
                'hero' => 'branded'],
        ];
    }

    public function run(): void
    {
        // Row 1 — the built-in, authoritative every run.
        ThemePreset::updateOrCreate(
            ['slug' => 'naara-official'],
            [
                'name' => 'Naara Official',
                'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons. Permanent default.',
                'tokens' => [
                    'colors' => ['primary' => '10 110 110', 'primary_dark' => '8 85 85', 'accent' => '212 160 23', 'accent_dark' => '148 112 16', 'navy' => '13 27 42', 'action' => '232 65 42'],
                    'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                    'typography' => ['display' => 'Supreme Display', 'sans' => 'Didact Gothic'],
                    'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                ],
                'icon_family' => ['style' => '3d', 'set' => 'default'],
                // Default home hero (owner reference) — the traveller/balloons scene
                // behind "My Connectivity". An admin HeroBackground upload still wins.
                'hero_assets' => ['dashboard' => '/img/themes/balloons.webp'],
                'layout_variants' => $this->baselineVariants(),
                'is_built_in' => true,
                'sort_order' => 1,
            ],
        );

        // The data-forward personas lead with wallet + connectivity state on the
        // dashboard home (variant-b); everyone else keeps the baseline (variant-a).
        // Only pages whose variant partials EXIST are assigned non-baseline values,
        // so nothing renders half-wired.
        $dashboardB = ['fintra-clean', 'genius-grid', 'slate-signal', 'capable-mono'];
        // eSIM variant-b = header/tiles lead, hero below (editorial/minimal personas).
        $esimB = ['paperwhite', 'aries-contrast', 'capable-mono'];
        // Numbers variant-b = the six-card bento leads, hero below (action-first personas).
        $numbersB = ['coral-current', 'origin-bold', 'waitlisty-soft'];

        // Per-theme home hero art (owner request): applying a theme swaps the
        // dashboard hero to its own image. Seeded under public/img/themes/. Eight
        // unique images cover the fourteen personas; the reuse is logged in
        // docs/build-specs/THEME-PLACEHOLDER-ASSETS.md for a later 1:1 swap.
        $hero = fn (string $f) => ['dashboard' => "/img/themes/{$f}.webp"];
        $heroMap = [
            'aurora-shift' => $hero('islands-female'),
            'sunset-transit' => $hero('balloons'),
            'midnight-signal' => $hero('portal-gateway'),
            'paperwhite' => $hero('app-ui-phone'),
            'fintra-clean' => $hero('before-after'),
            'origin-bold' => $hero('branded'),
            'capable-mono' => $hero('app-ui-phone'),
            'waitlisty-soft' => $hero('balloons'),
            'genius-grid' => $hero('before-after'),
            'lander-hero' => $hero('worldwide'),
            'aries-contrast' => $hero('portal-gateway'),
            'emerald-route' => $hero('islands-male'),
            'coral-current' => $hero('islands-female'),
            'slate-signal' => $hero('worldwide'),
        ];

        // Rows 2–15 — original personas. firstOrCreate = never clobber admin tuning.
        foreach ($this->presets() as $preset) {
            $variants = $this->baselineVariants();
            if (in_array($preset['slug'], $dashboardB, true)) {
                $variants['dashboard_home'] = 'variant-b';
            }
            if (in_array($preset['slug'], $esimB, true)) {
                $variants['esim'] = 'variant-b';
            }
            if (in_array($preset['slug'], $numbersB, true)) {
                $variants['numbers'] = 'variant-b';
            }

            ThemePreset::firstOrCreate(['slug' => $preset['slug']], [
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => [
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ],
                'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
                'hero_assets' => $heroMap[$preset['slug']] ?? [],
                'layout_variants' => $variants,
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
            ]);
        }

        // Rows 16–20 — the 5 brand-new personas added alongside the palette
        // refresh. Same firstOrCreate discipline: never clobber admin tuning.
        foreach ($this->newPresets() as $preset) {
            ThemePreset::firstOrCreate(['slug' => $preset['slug']], [
                'name' => $preset['name'],
                'persona' => $preset['persona'],
                'tokens' => [
                    'colors' => $preset['colors'],
                    'radius' => $preset['radius'],
                    'typography' => $preset['typography'],
                    'surface' => $preset['surface'],
                ],
                'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
                'hero_assets' => $hero($preset['hero']),
                'layout_variants' => $this->baselineVariants(),
                'is_built_in' => false,
                'sort_order' => $preset['sort_order'],
            ]);
        }
    }

    /**
     * The baseline page→variant map. Every page starts on variant-a (the
     * extracted current markup); §2 assigns variant-b per persona only where the
     * partial exists. Pages without a built variant stay on variant-a forever via
     * ThemePreset::layoutVariant()'s default, so this map can stay conservative.
     */
    private function baselineVariants(): array
    {
        return array_fill_keys(
            ['dashboard_home', 'esim', 'numbers', 'my_line', 'account_settings', 'profile', 'menu'],
            'variant-a',
        );
    }

    /**
     * The 14 original persona slugs, RECOLOURED (owner request, 2026-09-04):
     * `name`/`persona`/`colors` are new for every row here; `sort_order`,
     * `radius`, `typography` and `surface` are UNCHANGED from the original
     * seed, so every existing layout-variant assignment, hero-art mapping and
     * font choice stays valid with zero other wiring touched. Colours are
     * channel triples ("R G B"); every `primary` is kept dark enough for
     * white button text, matching the bar the original palette set.
     *
     * @return list<array<string,mixed>>
     */
    private function presets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'aurora-shift', 'name' => 'Indigo Current', 'sort_order' => 2,
                'persona' => 'Deep indigo-violet fintech energy with electric-blue highlights on a near-black gradient.',
                'colors' => ['primary' => '59 63 140', 'primary_dark' => '38 41 90', 'accent' => '76 111 255', 'accent_dark' => '72 105 242', 'navy' => '15 16 36', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'sunset-transit', 'name' => 'Boarding Pass', 'sort_order' => 3,
                'persona' => 'Airline-ticket navy with a warm coral pop — departures-board energy for a travel brand.',
                'colors' => ['primary' => '30 58 95', 'primary_dark' => '20 41 66', 'accent' => '242 132 107', 'accent_dark' => '169 92 75', 'navy' => '22 34 58', 'action' => '216 70 48'],
                'radius' => ['control' => '0.5rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'midnight-signal', 'name' => 'Midnight Signal', 'sort_order' => 4,
                'persona' => 'Near-black smart-home dark mode, cyan-teal accents, dark-first design.',
                'colors' => ['primary' => '16 124 132', 'primary_dark' => '11 92 98', 'accent' => '34 211 238', 'accent_dark' => '20 127 143', 'navy' => '16 20 28', 'action' => '244 63 94'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.4']],

            ['slug' => 'paperwhite', 'name' => 'Paperwhite', 'sort_order' => 5,
                'persona' => 'Ultra-light, high-whitespace, ink-black type on paper — minimal, editorial, zero noise.',
                'colors' => ['primary' => '17 18 20', 'primary_dark' => '0 0 0', 'accent' => '139 139 150', 'accent_dark' => '111 111 120', 'navy' => '28 28 31', 'action' => '185 55 40'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'none', 'card_border_opacity' => '0.8']],

            ['slug' => 'fintra-clean', 'name' => 'Ledger', 'sort_order' => 6,
                'persona' => 'Fintech slate-blue dashboards with a champagne-gold action colour, dense tabular numbers.',
                'colors' => ['primary' => '46 63 99', 'primary_dark' => '32 44 70', 'accent' => '240 169 59', 'accent_dark' => '144 101 35', 'navy' => '23 32 58', 'action' => '225 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7']],

            ['slug' => 'origin-bold', 'name' => 'Origin Bold', 'sort_order' => 7,
                'persona' => 'Vivid construction-orange with graphite accents, oversized type, confident colour blocks.',
                'colors' => ['primary' => '214 88 26', 'primary_dark' => '163 66 16', 'accent' => '23 23 23', 'accent_dark' => '23 23 23', 'navy' => '26 17 10', 'action' => '236 72 53'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'capable-mono', 'name' => 'Capable', 'sort_order' => 8,
                'persona' => 'Near-black monochrome with a single neon-lime accent — restrained by day, electric by night.',
                'colors' => ['primary' => '22 24 26', 'primary_dark' => '0 0 0', 'accent' => '198 255 0', 'accent_dark' => '99 128 0', 'navy' => '10 10 10', 'action' => '220 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'waitlisty-soft', 'name' => 'Horizon', 'sort_order' => 9,
                'persona' => 'Soft violet-purple with a magenta-pink pop, rounded-everything, approachable consumer feel.',
                'colors' => ['primary' => '109 63 160', 'primary_dark' => '79 45 120', 'accent' => '232 121 249', 'accent_dark' => '162 85 174', 'navy' => '30 20 45', 'action' => '236 90 70'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4']],

            ['slug' => 'genius-grid', 'name' => 'Grid Nine', 'sort_order' => 10,
                'persona' => 'Structured indigo-charcoal grid dashboard with an amber pop, information-dense home.',
                'colors' => ['primary' => '41 37 64', 'primary_dark' => '28 25 46', 'accent' => '250 204 21', 'accent_dark' => '138 112 12', 'navy' => '18 18 24', 'action' => '225 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '0.75rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'lander-hero', 'name' => 'Skyline', 'sort_order' => 11,
                'persona' => 'Big single-hero marketing energy — deep indigo-slate with a coral call to action.',
                'colors' => ['primary' => '39 50 86', 'primary_dark' => '27 35 62', 'accent' => '242 132 107', 'accent_dark' => '169 92 75', 'navy' => '15 19 28', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'aries-contrast', 'name' => 'Aries', 'sort_order' => 12,
                'persona' => 'High-contrast black, white and gold, sharp corners — live-odds, luxury-travel energy.',
                'colors' => ['primary' => '10 10 10', 'primary_dark' => '0 0 0', 'accent' => '245 197 24', 'accent_dark' => '135 108 13', 'navy' => '8 8 10', 'action' => '200 50 40'],
                'radius' => ['control' => '0.125rem', 'card' => '0.25rem', 'pill' => '0.25rem'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.8']],

            ['slug' => 'emerald-route', 'name' => 'Emerald Route', 'sort_order' => 13,
                'persona' => 'Deep forest green with a warm terracotta accent, spa-fresh route/map motif.',
                'colors' => ['primary' => '31 61 43', 'primary_dark' => '20 42 30', 'accent' => '216 150 61', 'accent_dark' => '151 105 43', 'navy' => '14 26 18', 'action' => '220 70 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'coral-current', 'name' => 'Crimson Current', 'sort_order' => 14,
                'persona' => 'Bold crimson-burgundy with a gold pop, energetic sport/campaign feel.',
                'colors' => ['primary' => '140 20 48', 'primary_dark' => '105 14 36', 'accent' => '245 158 11', 'accent_dark' => '159 103 7', 'navy' => '26 10 14', 'action' => '230 110 40'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'slate-signal', 'name' => 'Velvet Reserve', 'sort_order' => 15,
                'persona' => 'Deep wine-maroon with champagne-gold accents — premium, exclusive, after-hours feel.',
                'colors' => ['primary' => '67 20 36', 'primary_dark' => '46 14 25', 'accent' => '224 168 64', 'accent_dark' => '146 109 42', 'navy' => '20 10 14', 'action' => '220 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],
        ];
    }
}
