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
        'brand.logo_product_light',
        'brand.logo_product_dark',
        'brand.logo_agency_light',
        'brand.logo_agency_dark',
        'brand.favicon',
        'brand.color_primary',
        'brand.color_accent',
        'brand.color_navy',
        'brand.color_action',
        'brand.radius',
        'brand.preloader_enabled',
    ];

    /** Brand default hex palette (mirrors app.css :root — CLAUDE.md Section 2). */
    public const COLOR_DEFAULTS = [
        'primary' => '#0A6E6E',
        'accent' => '#D4A017',
        'navy' => '#0D1B2A',
        'action' => '#E8412A',
    ];

    /** @return array<string, string> */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'name' => (string) (Setting::getValue('brand.name') ?: config('app.name', 'NaaraSim')),
                    'product_light' => (string) Setting::getValue('brand.logo_product_light', ''),
                    'product_dark' => (string) Setting::getValue('brand.logo_product_dark', ''),
                    'agency_light' => (string) Setting::getValue('brand.logo_agency_light', ''),
                    'agency_dark' => (string) Setting::getValue('brand.logo_agency_dark', ''),
                    'favicon' => (string) Setting::getValue('brand.favicon', ''),
                    'color_primary' => (string) Setting::getValue('brand.color_primary', ''),
                    'color_accent' => (string) Setting::getValue('brand.color_accent', ''),
                    'color_navy' => (string) Setting::getValue('brand.color_navy', ''),
                    'color_action' => (string) Setting::getValue('brand.color_action', ''),
                    'radius' => (string) Setting::getValue('brand.radius', ''),
                    'preloader_enabled' => (bool) Setting::getValue('brand.preloader_enabled', false),
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
            'product_light' => '', 'product_dark' => '',
            'agency_light' => '', 'agency_dark' => '',
            'favicon' => '',
            'color_primary' => '', 'color_accent' => '', 'color_navy' => '', 'color_action' => '',
            'radius' => '', 'preloader_enabled' => false,
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

    /** A logo URL for a variant/theme, or null to fall back to the wordmark. */
    public static function logo(string $variant, string $theme): ?string
    {
        $key = $variant.'_'.$theme; // e.g. product_light
        $url = self::current()[$key] ?? '';

        return $url !== '' ? $url : null;
    }

    /** True when at least one product logo has been uploaded. */
    public static function hasProductLogo(): bool
    {
        return self::logo('product', 'light') !== null || self::logo('product', 'dark') !== null;
    }

    public static function favicon(): ?string
    {
        $f = self::current()['favicon'];

        return $f !== '' ? $f : null;
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
