<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\ThemePreset as ThemePresetModel;
use App\Support\ThemePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NAARA THEME SYSTEM — Batch 1. The theme engine is paint-only and must fail
 * safe: naara-official emits NO css, junk tokens never reach the page, and a
 * missing row falls back to the built-in rather than blanking the platform.
 */
class ThemePresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ThemePreset::bust();
    }

    public function test_fresh_install_with_no_rows_falls_back_to_built_in(): void
    {
        // Empty table, no active-theme setting → synthetic naara-official.
        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('', ThemePreset::styleCss(), 'Built-in emits no override.');
        $this->assertSame('theme-naara-official', ThemePreset::bodyClass());
        $this->assertSame('3d', ThemePreset::iconFamily()['style']);
    }

    public function test_seeded_naara_official_still_emits_empty_css(): void
    {
        $this->seed(\Database\Seeders\ThemePresetSeeder::class);
        ThemePreset::bust();

        $this->assertSame('naara-official', ThemePreset::slug());
        // The built-in look already ships in app.css → zero injected CSS.
        $this->assertSame('', ThemePreset::styleCss());
    }

    public function test_a_custom_theme_emits_only_whitelisted_validated_vars(): void
    {
        ThemePresetModel::create([
            'slug' => 'aurora-shift',
            'name' => 'Aurora Shift',
            'tokens' => [
                'colors' => [
                    'primary' => '30 64 175',        // valid channel triple
                    'accent' => 'javascript:alert(1)', // junk → dropped
                    'action' => '999 0 0',            // out of range → dropped
                ],
                'radius' => ['card' => '2rem', 'pill' => '10px; } body{display:none'], // 2nd is junk
                'typography' => ['display' => 'Supreme Display', 'sans' => 'Comic Sans MS'], // 2nd not allow-listed
                'surface' => ['card_border_opacity' => '0.4'],
            ],
            'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'is_built_in' => false,
            'sort_order' => 2,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'aurora-shift');
        ThemePreset::bust();

        $css = ThemePreset::styleCss();

        // Wrapped in :root, valid tokens present…
        $this->assertStringStartsWith(':root{', $css);
        $this->assertStringContainsString('--brand-primary:30 64 175', $css);
        $this->assertStringContainsString('--radius-card:2rem', $css);
        $this->assertStringContainsString('--font-display:\'Supreme Display\'', $css);
        $this->assertStringContainsString('--card-border-opacity:0.4', $css);

        // …and every junk/out-of-range/non-allow-listed value dropped.
        $this->assertStringNotContainsString('javascript', $css);
        $this->assertStringNotContainsString('999', $css);
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringNotContainsString('Comic Sans', $css);
        $this->assertStringNotContainsString('body{', $css);
    }

    public function test_layout_variant_defaults_to_variant_a(): void
    {
        ThemePresetModel::create([
            'slug' => 'grid-nine', 'name' => 'Grid Nine',
            'tokens' => [], 'icon_family' => ['style' => 'sprite', 'set' => 'naara-sprite-01'],
            'layout_variants' => ['dashboard_home' => 'variant-c'],
            'is_built_in' => false, 'sort_order' => 3,
        ]);
        Setting::setValue(ThemePreset::SETTING_KEY, 'grid-nine');
        ThemePreset::bust();

        $this->assertSame('variant-c', ThemePreset::layoutVariant('dashboard_home'));
        $this->assertSame('variant-a', ThemePreset::layoutVariant('numbers'), 'Unset page → baseline.');
        $this->assertSame('variant-a', ThemePreset::layoutVariant('nonexistent_page'));
    }

    public function test_missing_active_row_falls_back_without_blanking(): void
    {
        // Point the setting at a slug that doesn't exist → built-in, never blank.
        Setting::setValue(ThemePreset::SETTING_KEY, 'does-not-exist');
        ThemePreset::bust();

        $this->assertSame('naara-official', ThemePreset::slug());
        $this->assertSame('', ThemePreset::styleCss());
    }
}
