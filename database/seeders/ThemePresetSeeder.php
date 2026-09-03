<?php

namespace Database\Seeders;

use App\Models\ThemePreset;
use Illuminate\Database\Seeder;

/**
 * NAARA THEME SYSTEM — Batch 2 §1. Seeds all 15 switchable presets.
 *
 * `naara-official` (row 1) is the permanent built-in: its tokens are TRANSCRIBED
 * from the live app.css `--brand-*` set and it emits NO override CSS (the built-in
 * look already ships), so it is re-seeded authoritatively every run.
 *
 * Rows 2–15 are ORIGINAL Naara personas — inspired by the layout-energy of the
 * reference templates the owner forwarded, never their copy/imagery/branding.
 * Each palette uses real hex values chosen so white text on the primary and dark
 * text on light surfaces clear WCAG AA; `primary` is deliberately kept dark
 * enough for white button text everywhere. They are seeded with `firstOrCreate`
 * so a re-run NEVER clobbers an admin's own tuning made through the picker.
 *
 * Icons: only `naara-official` keeps the 3D set; every other preset points at the
 * shared `naara-sprite-01` family (the sprite sheet itself ships in Batch 3 §5 —
 * until then icons render via the existing sprite, the flag is just stored data).
 * Structural `layout_variants` stay on `variant-a` here; Batch 2 §2 assigns
 * variant-b/variant-c once those partials exist, so nothing renders half-wired.
 */
class ThemePresetSeeder extends Seeder
{
    public function run(): void
    {
        // Row 1 — the built-in, authoritative every run.
        ThemePreset::updateOrCreate(
            ['slug' => 'naara-official'],
            [
                'name' => 'Naara Official',
                'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons. Permanent default.',
                'tokens' => [
                    'colors' => ['primary' => '10 110 110', 'primary_dark' => '8 85 85', 'accent' => '212 160 23', 'navy' => '13 27 42', 'action' => '232 65 42'],
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
     * The 14 original presets. Colours are channel triples ("R G B"); primaries
     * are all dark enough for white text (AA). Radii/typography/surface vary to
     * give each persona its own feel without any Blade change.
     *
     * @return list<array<string,mixed>>
     */
    private function presets(): array
    {
        $display = 'Supreme Display';

        return [
            ['slug' => 'aurora-shift', 'name' => 'Aurora Shift', 'sort_order' => 2,
                'persona' => 'Cooler teal-to-indigo gradient hero bands, glassmorphic wallet card.',
                'colors' => ['primary' => '30 110 140', 'primary_dark' => '20 78 100', 'accent' => '129 140 248', 'navy' => '11 22 40', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'sunset-transit', 'name' => 'Sunset Transit', 'sort_order' => 3,
                'persona' => 'Warm coral/gold-forward, travel-photography heroes.',
                'colors' => ['primary' => '180 74 48', 'primary_dark' => '140 55 35', 'accent' => '226 160 60', 'navy' => '33 20 18', 'action' => '216 70 48'],
                'radius' => ['control' => '0.5rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'midnight-signal', 'name' => 'Midnight Signal', 'sort_order' => 4,
                'persona' => 'Near-black navy surfaces, neon-teal accents, dark-first design.',
                'colors' => ['primary' => '13 148 136', 'primary_dark' => '10 110 100', 'accent' => '45 212 191', 'navy' => '6 11 18', 'action' => '244 63 94'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.4']],

            ['slug' => 'paperwhite', 'name' => 'Paperwhite', 'sort_order' => 5,
                'persona' => 'Ultra-light, high-whitespace, minimal borders, editorial typography.',
                'colors' => ['primary' => '23 37 84', 'primary_dark' => '15 23 42', 'accent' => '120 113 108', 'navy' => '30 41 59', 'action' => '185 55 40'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'none', 'card_border_opacity' => '0.8']],

            ['slug' => 'fintra-clean', 'name' => 'Ledger', 'sort_order' => 6,
                'persona' => 'Fintech-inspired dense data cards, tabular wallet numbers, crisp rules.',
                'colors' => ['primary' => '22 78 99', 'primary_dark' => '12 55 70', 'accent' => '16 185 129', 'navy' => '15 23 42', 'action' => '225 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '0.75rem', 'pill' => '0.5rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.7']],

            ['slug' => 'origin-bold', 'name' => 'Origin Bold', 'sort_order' => 7,
                'persona' => 'Oversized display type, big rounded pill CTAs, confident colour blocks.',
                'colors' => ['primary' => '79 70 229', 'primary_dark' => '55 48 163', 'accent' => '245 158 11', 'navy' => '17 24 39', 'action' => '236 72 53'],
                'radius' => ['control' => '0.75rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'capable-mono', 'name' => 'Capable', 'sort_order' => 8,
                'persona' => 'Near-monochrome + single teal accent, restrained, enterprise-feel.',
                'colors' => ['primary' => '15 118 110', 'primary_dark' => '10 85 80', 'accent' => '100 116 139', 'navy' => '17 24 39', 'action' => '220 60 45'],
                'radius' => ['control' => '0.375rem', 'card' => '1rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'waitlisty-soft', 'name' => 'Horizon', 'sort_order' => 9,
                'persona' => 'Soft pastel gradients, rounded-everything, approachable/consumer.',
                'colors' => ['primary' => '91 78 220', 'primary_dark' => '67 56 202', 'accent' => '244 114 182', 'navy' => '30 27 75', 'action' => '236 90 70'],
                'radius' => ['control' => '0.875rem', 'card' => '2rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.4']],

            ['slug' => 'genius-grid', 'name' => 'Grid Nine', 'sort_order' => 10,
                'persona' => 'Structured grid dashboard, card-heavy, information-dense home.',
                'colors' => ['primary' => '13 148 136', 'primary_dark' => '15 118 110', 'accent' => '234 179 8', 'navy' => '17 24 39', 'action' => '225 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1rem', 'pill' => '0.75rem'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],

            ['slug' => 'lander-hero', 'name' => 'Skyline', 'sort_order' => 11,
                'persona' => 'Big single-hero-first marketing pages, dashboard mirrors that scale.',
                'colors' => ['primary' => '37 99 235', 'primary_dark' => '29 78 216', 'accent' => '14 165 233', 'navy' => '15 23 42', 'action' => '232 65 42'],
                'radius' => ['control' => '0.625rem', 'card' => '1.75rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'lg', 'card_border_opacity' => '0.5']],

            ['slug' => 'aries-contrast', 'name' => 'Aries', 'sort_order' => 12,
                'persona' => 'High-contrast black/white/gold, sharp corners, luxury-travel feel.',
                'colors' => ['primary' => '17 24 39', 'primary_dark' => '0 0 0', 'accent' => '212 160 23', 'navy' => '10 10 12', 'action' => '200 50 40'],
                'radius' => ['control' => '0.125rem', 'card' => '0.25rem', 'pill' => '0.25rem'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.8']],

            ['slug' => 'emerald-route', 'name' => 'Emerald Route', 'sort_order' => 13,
                'persona' => 'Deep emerald + sand palette, map/route motif throughout.',
                'colors' => ['primary' => '16 122 87', 'primary_dark' => '12 90 64', 'accent' => '205 170 120', 'navy' => '12 30 24', 'action' => '220 70 50'],
                'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'coral-current', 'name' => 'Coral Current', 'sort_order' => 14,
                'persona' => 'Coral/action-red forward, energetic, youth-travel positioning.',
                'colors' => ['primary' => '200 60 45', 'primary_dark' => '160 45 34', 'accent' => '245 158 11', 'navy' => '28 16 14', 'action' => '200 60 45'],
                'radius' => ['control' => '0.625rem', 'card' => '1.5rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Figtree'],
                'surface' => ['card_shadow' => 'md', 'card_border_opacity' => '0.5']],

            ['slug' => 'slate-signal', 'name' => 'Slate Signal', 'sort_order' => 15,
                'persona' => 'Cool slate greys + single teal pop, quiet/professional.',
                'colors' => ['primary' => '51 65 85', 'primary_dark' => '30 41 59', 'accent' => '20 184 166', 'navy' => '15 23 42', 'action' => '220 60 45'],
                'radius' => ['control' => '0.5rem', 'card' => '1.25rem', 'pill' => '9999px'],
                'typography' => ['display' => $display, 'sans' => 'Didact Gothic'],
                'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6']],
        ];
    }
}
