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
        ];
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
