<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed brand assets (Module 26). Stores the uploaded logo set + brand
 * name so the whole platform re-brands with no redeploy. Each logo has a light
 * and dark variant so both themes look right; the favicon/app icon feeds the
 * browser tab and installable app icon. Values are public URLs (served from the
 * public/Wasabi disk via MediaStorage). Cached; busted on any brand.* save.
 *
 * Everything falls back gracefully to the text wordmark + built-in icon until a
 * logo is uploaded, so a fresh install always looks intentional.
 */
class BrandSettings
{
    private const CACHE_KEY = 'brand.settings';

    /** Setting keys this feature owns (for upload + cache-bust). */
    public const KEYS = [
        'brand.name',
        'brand.logo_family_light',
        'brand.logo_family_dark',
        'brand.logo_product_light',
        'brand.logo_product_dark',
        'brand.logo_agency_light',
        'brand.logo_agency_dark',
        'brand.logo_gift_light',
        'brand.logo_gift_dark',
        'brand.favicon',
        'brand.color_primary',
        'brand.color_accent',
        'brand.color_navy',
        'brand.color_action',
        'brand.radius',
        'brand.preloader_enabled',
        'brand.preloader_style',
    ];

    /** Preloader visual styles the admin can pick (blueprint audit §7). */
    public const PRELOADER_STYLES = ['pulse-logo', 'spinner', 'bars', 'progress'];

    /** Brand default hex palette (mirrors app.css :root — CLAUDE.md Section 2). */
    public const COLOR_DEFAULTS = [
        'primary' => '#0A6E6E',
        'accent' => '#D4A017',
        'navy' => '#0D1B2A',
        'action' => '#E8412A',
    ];

    /**
     * The official brand logos shipped with the product (committed in public/brand,
     * transparent PNG). These are the DEFAULTS — an admin upload (brand.* setting)
     * always wins, but a fresh install renders the real NaaraSim + Supreme Ideas
     * Agency marks out of the box instead of the text wordmark.
     */
    public const LOGO_DEFAULTS = [
        // family = the umbrella "Naara" mark (home dashboard, marketing, Aurora
        // welcome); product = NaaraSim (eSIM + number surfaces); gift = Naara
        // Gift (the gift storefront); agency = Supreme Ideas Agency.
        'family_light' => '/brand/naara-family-light.png',
        'family_dark' => '/brand/naara-family-dark.png',
        'product_light' => '/brand/naarasim-product-light.png',
        'product_dark' => '/brand/naarasim-product-dark.png',
        'gift_light' => '/brand/naara-gift-light.png',
        'gift_dark' => '/brand/naara-gift-dark.png',
        'agency_light' => '/brand/supreme-ideas-light.png',
        'agency_dark' => '/brand/supreme-ideas-dark.png',
        'favicon' => '/brand/naarasim-favicon.png',
    ];

    /** @return array<string, string> */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'name' => (string) (Setting::getValue('brand.name') ?: config('app.name', 'NaaraSim')),
                    'family_light' => (string) Setting::getValue('brand.logo_family_light', ''),
                    'family_dark' => (string) Setting::getValue('brand.logo_family_dark', ''),
                    'product_light' => (string) Setting::getValue('brand.logo_product_light', ''),
                    'product_dark' => (string) Setting::getValue('brand.logo_product_dark', ''),
                    'agency_light' => (string) Setting::getValue('brand.logo_agency_light', ''),
                    'agency_dark' => (string) Setting::getValue('brand.logo_agency_dark', ''),
                    'gift_light' => (string) Setting::getValue('brand.logo_gift_light', ''),
                    'gift_dark' => (string) Setting::getValue('brand.logo_gift_dark', ''),
                    'favicon' => (string) Setting::getValue('brand.favicon', ''),
                    'color_primary' => (string) Setting::getValue('brand.color_primary', ''),
                    'color_accent' => (string) Setting::getValue('brand.color_accent', ''),
                    'color_navy' => (string) Setting::getValue('brand.color_navy', ''),
                    'color_action' => (string) Setting::getValue('brand.color_action', ''),
                    'radius' => (string) Setting::getValue('brand.radius', ''),
                    'preloader_enabled' => (bool) Setting::getValue('brand.preloader_enabled', false),
                    'preloader_style' => (string) Setting::getValue('brand.preloader_style', ''),
                    // Admin-tunable per-logo scale (multiplier). Default 1 = shipped size.
                    'logo_scale_family' => (string) Setting::getValue('brand.logo_scale_family', ''),
                    'logo_scale_product' => (string) Setting::getValue('brand.logo_scale_product', ''),
                    'logo_scale_gift' => (string) Setting::getValue('brand.logo_scale_gift', ''),
                ];
            } catch (\Throwable) {
                return self::defaults();
            }
        });
    }

    /** @return array<string, string> */
    private static function defaults(): array
    {
        return [
            'name' => config('app.name', 'NaaraSim'),
            'family_light' => '', 'family_dark' => '',
            'product_light' => '', 'product_dark' => '',
            'agency_light' => '', 'agency_dark' => '',
            'gift_light' => '', 'gift_dark' => '',
            'favicon' => '',
            'color_primary' => '', 'color_accent' => '', 'color_navy' => '', 'color_action' => '',
            'radius' => '', 'preloader_enabled' => false, 'preloader_style' => '',
        ];
    }

    /** A brand colour (hex), admin override or the default. */
    public static function color(string $key): string
    {
        $v = self::current()['color_'.$key] ?? '';

        return $v !== '' ? $v : (self::COLOR_DEFAULTS[$key] ?? '#000000');
    }

    public static function radius(): string
    {
        $v = self::current()['radius'] ?? '';

        return $v !== '' ? $v : '0.5rem';
    }

    public static function preloaderEnabled(): bool
    {
        return (bool) (self::current()['preloader_enabled'] ?? false);
    }

    /** Admin-tunable size multiplier for a logo variant (0.5–2.0; default 1.0). */
    public static function logoScale(string $variant): float
    {
        // Gift/agency use their own key; product + family have theirs; anything
        // else falls back to 1.0 (no scaling).
        $key = in_array($variant, ['family', 'product', 'gift'], true) ? $variant : null;
        if ($key === null) {
            return 1.0;
        }
        $v = (float) (self::current()['logo_scale_'.$key] ?? 0);

        return $v > 0 ? max(0.5, min(2.0, $v)) : 1.0;
    }

    /** The chosen preloader style; defaults to the pulsing logo (audit §7). */
    public static function preloaderStyle(): string
    {
        $v = (string) (self::current()['preloader_style'] ?? '');

        return in_array($v, self::PRELOADER_STYLES, true) ? $v : 'pulse-logo';
    }

    /** True once the admin has overridden any colour or the radius. */
    public static function hasThemeOverride(): bool
    {
        $c = self::current();

        return filled($c['color_primary'] ?? '') || filled($c['color_accent'] ?? '')
            || filled($c['color_navy'] ?? '') || filled($c['color_action'] ?? '')
            || filled($c['radius'] ?? '');
    }

    /**
     * The runtime override CSS injected into the layout head. Emits only the
     * :root brand variables that differ from the defaults, as channel triples
     * so Tailwind's rgb(var(--brand-*) / <alpha>) colours keep working.
     * Returns '' when nothing is customised (no wasted <style>).
     */
    public static function themeCss(): string
    {
        if (! self::hasThemeOverride()) {
            return '';
        }

        $lines = [];
        $map = [
            'color_primary' => 'brand-primary',
            'color_accent' => 'brand-accent',
            'color_navy' => 'brand-navy',
            'color_action' => 'brand-action',
        ];
        $c = self::current();
        foreach ($map as $settingKey => $cssVar) {
            $hex = $c[$settingKey] ?? '';
            if ($hex !== '' && ($rgb = self::hexToChannels($hex)) !== null) {
                $lines[] = "--{$cssVar}: {$rgb};";
                if ($cssVar === 'brand-primary') {
                    $lines[] = '--brand-primary-dark: '.self::darken($hex).';';
                }
            }
        }
        if (($c['radius'] ?? '') !== '') {
            $lines[] = '--brand-radius: '.self::sanitizeRadius($c['radius']).';';
        }

        return $lines === [] ? '' : ':root{'.implode('', $lines).'}';
    }

    /** "#0A6E6E" => "10 110 110" (channel triple), or null if malformed. */
    public static function hexToChannels(string $hex): ?string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return hexdec(substr($hex, 0, 2)).' '.hexdec(substr($hex, 2, 2)).' '.hexdec(substr($hex, 4, 2));
    }

    /** A ~20% darker channel triple for the primary-dark hover token. */
    private static function darken(string $hex): string
    {
        $rgb = self::hexToChannels($hex);
        if ($rgb === null) {
            return '8 85 85';
        }

        return implode(' ', array_map(fn ($c) => (int) round((int) $c * 0.78), explode(' ', $rgb)));
    }

    /** Clamp the radius to a safe rem value (defends the injected CSS). */
    private static function sanitizeRadius(string $radius): string
    {
        return preg_match('/^\d?\.?\d+rem$/', trim($radius)) ? trim($radius) : '0.5rem';
    }

    public static function name(): string
    {
        return self::current()['name'];
    }

    /**
     * The admin-uploaded logo URL for a variant/theme, or null if none. The
     * per-variant value is intentionally NOT defaulted here — the light<->dark
     * cross-fallback and the shipped-default fallback live in <x-brand-logo> /
     * resolvedLogo(), so a single admin upload still serves both themes.
     */
    public static function logo(string $variant, string $theme): ?string
    {
        $key = $variant.'_'.$theme; // e.g. product_light
        $url = self::current()[$key] ?? '';

        return $url !== '' ? $url : null;
    }

    /**
     * Display URL for a variant/theme: admin upload for that theme → admin
     * upload for the other theme → the shipped brand default. Always returns a
     * URL, so the real logo shows out of the box and any admin upload wins.
     */
    public static function resolvedLogo(string $variant, string $theme): ?string
    {
        $other = $theme === 'light' ? 'dark' : 'light';

        return self::logo($variant, $theme)
            ?? self::logo($variant, $other)
            ?? (self::LOGO_DEFAULTS[$variant.'_'.$theme] ?? null);
    }

    /** True — a product logo always displays (admin upload or the shipped default). */
    public static function hasProductLogo(): bool
    {
        return self::resolvedLogo('product', 'light') !== null;
    }

    public static function favicon(): ?string
    {
        $f = self::current()['favicon'] ?? '';

        return $f !== '' ? $f : self::LOGO_DEFAULTS['favicon'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isBrandKey(string $key): bool
    {
        return str_starts_with($key, 'brand.');
    }
}
