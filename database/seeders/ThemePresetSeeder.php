<?php

namespace Database\Seeders;

use App\Models\ThemePreset;
use Illuminate\Database\Seeder;

/**
 * NAARA THEME SYSTEM — Batch 1 §1.1. Seeds the one built-in preset,
 * `naara-official`: the current shipped look, frozen as the permanent default
 * and fallback. Its token values are TRANSCRIBED from the live
 * resources/css/app.css `--brand-*` set — not invented — so the admin picker's
 * swatch strip shows the real brand colours. Because the built-in look already
 * ships in app.css, ThemePreset::styleCss() emits nothing for this row; the
 * tokens here are reference/display only.
 *
 * Idempotent: safe to re-run every deploy. Batch 2 adds the other 14 presets to
 * this same seeder with the "don't clobber an admin's own tuning" guard.
 */
class ThemePresetSeeder extends Seeder
{
    public function run(): void
    {
        ThemePreset::updateOrCreate(
            ['slug' => 'naara-official'],
            [
                'name' => 'Naara Official',
                'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons. Permanent default.',
                'tokens' => [
                    'colors' => [
                        'primary' => '10 110 110',      // Deep Teal   #0A6E6E
                        'primary_dark' => '8 85 85',    // Teal Dark   #085555
                        'accent' => '212 160 23',       // Warm Gold   #D4A017
                        'navy' => '13 27 42',           // Midnight Navy #0D1B2A
                        'action' => '232 65 42',        // Coral Red   #E8412A
                    ],
                    'radius' => ['control' => '0.5rem', 'card' => '1.5rem', 'pill' => '9999px'],
                    'typography' => ['display' => 'Supreme Display', 'sans' => 'Didact Gothic'],
                    'surface' => ['card_shadow' => 'sm', 'card_border_opacity' => '0.6'],
                ],
                'icon_family' => ['style' => '3d', 'set' => 'default'],
                'hero_assets' => [],
                // Baseline composition on every page — variant-a is the extracted
                // current markup, so naara-official stays pixel-identical.
                'layout_variants' => [
                    'dashboard_home' => 'variant-a',
                    'esim' => 'variant-a',
                    'numbers' => 'variant-a',
                    'my_line' => 'variant-a',
                    'account_settings' => 'variant-a',
                    'profile' => 'variant-a',
                    'menu' => 'variant-a',
                ],
                'is_built_in' => true,
                'sort_order' => 1,
            ],
        );
    }
}
