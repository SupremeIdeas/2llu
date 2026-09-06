<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * NAARA THEME SYSTEM — Batch 1 §1.2. The switchable-skin engine, a direct
 * sibling of PlatformTheme.
 *
 * A theme is PAINT, never plumbing. This class only ever emits a small,
 * strictly-whitelisted <style> block of `:root` CSS-variable overrides and a
 * single `theme-{slug}` body class — the exact same runtime-override discipline
 * BrandSettings::themeCss() and PlatformTheme::styleCss() already use. No
 * business logic, no Livewire fork, no route touches a theme. The built-in
 * `naara-official` look already ships in app.css, so its override is the empty
 * string — selecting it is a true no-op visually.
 *
 * Security property (the single most important one): admins can add or edit
 * themes, but NO theme, however it is stored, can inject arbitrary CSS or
 * script. styleCss() emits only known variable names, and every token value is
 * re-validated at read time against a strict schema (channel-triple colours,
 * bounded radii, an enumerated font allow-list). Anything out of range is
 * dropped, not emitted.
 */
class ThemePreset
{
    private const CACHE_KEY = 'theme.active.v1';

    /** Setting key that stores the active preset's slug. */
    public const SETTING_KEY = 'platform.active_theme';

    /** The permanent default + fallback slug — its CSS override is always empty. */
    public const DEFAULT_SLUG = 'naara-official';

    /** The three structural layout partials a page may pick between. */
    public const VARIANTS = ['variant-a', 'variant-b', 'variant-c'];

    /**
     * Font families a theme is allowed to name. Every value must ALSO be
     * registered in tailwind.config.js + loaded via @font-face / Google Fonts
     * at build time — never injected per-theme at runtime (FOUT/CLS risk,
     * CLAUDE.md §S24). Batch 2 extends this list once, up front, with the 15
     * presets' fonts; Batch 1 ships only what the current look already uses.
     */
    private const FONT_ALLOW = [
        'Supreme Display', 'Didact Gothic', 'Figtree',
    ];

    /**
     * The active preset, array-shaped (tokens/icon_family/hero_assets/
     * layout_variants already decoded). Cached forever, busted only on admin
     * save. Fails safe to a synthetic naara-official on any error (missing
     * table on a fresh install, corrupted row, mid-migration) — exactly
     * PlatformTheme::current()'s try/catch discipline.
     *
     * @return array{slug:string,name:string,persona:?string,tokens:array,icon_family:array,hero_assets:array,layout_variants:array,is_built_in:bool}
     */
    public static function active(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $slug = Setting::getValue(self::SETTING_KEY) ?: self::DEFAULT_SLUG;
                $row = DB::table('theme_presets')->where('slug', $slug)->first();

                // Missing active row → fall back to the built-in, never blank.
                if ($row === null) {
                    $row = DB::table('theme_presets')->where('slug', self::DEFAULT_SLUG)->first();
                }
                if ($row === null) {
                    return self::synthetic();
                }

                return [
                    'slug' => (string) $row->slug,
                    'name' => (string) $row->name,
                    'persona' => $row->persona,
                    'tokens' => self::decode($row->tokens),
                    'icon_family' => self::decode($row->icon_family),
                    'hero_assets' => self::decode($row->hero_assets),
                    'layout_variants' => self::decode($row->layout_variants),
                    'is_built_in' => (bool) $row->is_built_in,
                ];
            } catch (\Throwable) {
                return self::synthetic();
            }
        });
    }

    public static function slug(): string
    {
        return self::active()['slug'];
    }

    /** @return array the decoded, un-validated token bag (styleCss validates on emit). */
    public static function tokens(): array
    {
        return self::active()['tokens'];
    }

    public static function iconFamily(): array
    {
        $fam = self::active()['icon_family'];

        return [
            'style' => in_array($fam['style'] ?? null, ['3d', 'sprite'], true) ? $fam['style'] : '3d',
            'set' => is_string($fam['set'] ?? null) && preg_match('/^[a-z0-9\-]{1,40}$/', $fam['set']) ? $fam['set'] : 'default',
        ];
    }

    /** A single body class the CSS and any presentational @class can key off. */
    public static function bodyClass(): string
    {
        return 'theme-'.self::slug();
    }

    /**
     * The active theme's hero image for a surface ('dashboard'|'esim'|'numbers'),
     * or null if it has none. Each preset carries its own hero art in
     * hero_assets, so applying a theme swaps the home hero. Only same-origin
     * paths / http(s) URLs pass — never arbitrary strings reaching an <img src>.
     */
    public static function heroFor(string $surface): ?string
    {
        $val = self::active()['hero_assets'][$surface] ?? null;
        if (! is_string($val) || $val === '') {
            return null;
        }

        return preg_match('#^(/[\w./-]+|https?://[\w./:?=&%-]+)$#', $val) === 1 ? $val : null;
    }

    /**
     * Which structural partial a given page uses under the active theme.
     * Defaults to variant-a (the extracted current markup) for any page/theme
     * combination not explicitly set, so a missing key never 500s.
     */
    public static function layoutVariant(string $page): string
    {
        $pick = self::active()['layout_variants'][$page] ?? null;

        return in_array($pick, self::VARIANTS, true) ? $pick : 'variant-a';
    }

    /**
     * The runtime <style> body. EMPTY for the built-in naara-official (app.css
     * already renders that look — no diff, no regression). For every other
     * theme, emit only the whitelisted `:root` variable overrides, each value
     * re-validated here so nothing arbitrary can reach the page.
     */
    public static function styleCss(): string
    {
        $preset = self::active();
        if ($preset['is_built_in'] || $preset['slug'] === self::DEFAULT_SLUG) {
            return '';
        }

        $vars = self::emitVars($preset['tokens']);

        return $vars === '' ? '' : ':root{'.$vars.'}';
    }

    public static function bust(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The browser-chrome colour (mobile address bar / task-switcher card) for
     * the active theme, as a hex string — or null on the built-in default,
     * where the admin's App Export `theme_color` still applies unchanged.
     * Owner request: switching a Theme Preset should repaint the browser
     * chrome too, not just the in-app surfaces.
     */
    public static function browserThemeColor(): ?string
    {
        $preset = self::active();
        if ($preset['is_built_in'] || $preset['slug'] === self::DEFAULT_SLUG) {
            return null;
        }

        $primary = $preset['tokens']['colors']['primary'] ?? null;
        if (! self::validChannelTriple($primary)) {
            return null;
        }

        [$r, $g, $b] = array_map('intval', explode(' ', $primary));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** Every preset, for the admin picker (Batch 2). Ordered by sort_order. */
    public static function all(): Collection
    {
        try {
            return collect(DB::table('theme_presets')->orderBy('sort_order')->get())
                ->map(fn ($r) => [
                    'slug' => (string) $r->slug,
                    'name' => (string) $r->name,
                    'persona' => $r->persona,
                    'tokens' => self::decode($r->tokens),
                    'icon_family' => self::decode($r->icon_family),
                    'is_built_in' => (bool) $r->is_built_in,
                    'sort_order' => (int) $r->sort_order,
                ]);
        } catch (\Throwable) {
            return collect([self::synthetic()]);
        }
    }

    /**
     * Build the whitelisted `--var:value;` string from a token bag. Only known
     * keys are considered and each value must pass its validator or it is
     * silently dropped — the injection-safety guarantee.
     */
    private static function emitVars(array $tokens): string
    {
        $out = [];

        // Colours — channel triples ("R G B"), reused by Tailwind's
        // rgb(var(--brand-*) / <alpha>) pattern with zero config changes.
        $colorMap = [
            'primary' => '--brand-primary',
            'primary_dark' => '--brand-primary-dark',
            'accent' => '--brand-accent',
            'navy' => '--brand-navy',
            'action' => '--brand-action',
        ];
        foreach ($colorMap as $key => $var) {
            $val = $tokens['colors'][$key] ?? null;
            if (self::validChannelTriple($val)) {
                $out[] = "{$var}:{$val}";
            }
        }

        // Radius — bounded length values. `control` also drives the existing
        // --brand-radius so today's buttons/inputs re-round with no Blade edit.
        $radiusMap = [
            'control' => '--radius-control',
            'card' => '--radius-card',
            'pill' => '--radius-pill',
        ];
        foreach ($radiusMap as $key => $var) {
            $val = $tokens['radius'][$key] ?? null;
            if (self::validLength($val)) {
                $out[] = "{$var}:{$val}";
                if ($key === 'control') {
                    $out[] = "--brand-radius:{$val}";
                }
            }
        }

        // Typography — enumerated font names only (must be tailwind-registered).
        $typoMap = ['display' => '--font-display', 'sans' => '--font-sans'];
        foreach ($typoMap as $key => $var) {
            $val = $tokens['typography'][$key] ?? null;
            if (is_string($val) && in_array($val, self::FONT_ALLOW, true)) {
                // Quote the family name; only allow-listed values reach here.
                $out[] = "{$var}:'{$val}'";
            }
        }

        // Surface — a single bounded numeric opacity for card borders.
        $borderOpacity = $tokens['surface']['card_border_opacity'] ?? null;
        if (self::validUnitInterval($borderOpacity)) {
            $out[] = "--card-border-opacity:{$borderOpacity}";
        }

        return $out === [] ? '' : implode(';', $out).';';
    }

    private static function validChannelTriple(mixed $v): bool
    {
        return is_string($v) && preg_match('/^\d{1,3} \d{1,3} \d{1,3}$/', $v) === 1
            && collect(explode(' ', $v))->every(fn ($n) => (int) $n >= 0 && (int) $n <= 255);
    }

    private static function validLength(mixed $v): bool
    {
        // e.g. 0.5rem, 1.5rem, 12px, 9999px, 0 — bounded, no calc()/expressions.
        return is_string($v) && preg_match('/^(0|\d{1,4}(\.\d{1,3})?(px|rem|em))$/', $v) === 1;
    }

    private static function validUnitInterval(mixed $v): bool
    {
        return (is_string($v) || is_numeric($v)) && preg_match('/^(0(\.\d{1,3})?|1(\.0{1,3})?)$/', (string) $v) === 1;
    }

    /** @return array decoded json, always an array. */
    private static function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** The synthetic built-in used when the table/row isn't available yet. */
    private static function synthetic(): array
    {
        return [
            'slug' => self::DEFAULT_SLUG,
            'name' => 'Naara Official',
            'persona' => 'Current shipped look — deep teal, warm gold, midnight navy, 3D icons.',
            'tokens' => [],
            'icon_family' => ['style' => '3d', 'set' => 'default'],
            'hero_assets' => [],
            'layout_variants' => [],
            'is_built_in' => true,
        ];
    }
}
