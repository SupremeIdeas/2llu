<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Aurora Welcome Animation settings (first-login premium entrance). Admin-tunable
 * via Setting rows, resilient to a missing table (mirrors BrandSettings/AppExport)
 * so the entrance never 500s the app. Colours default to the existing brand
 * palette; timings are in milliseconds (aurora_speed is in seconds — the CSS
 * blob loop). Nothing here is user-facing cost/secret data.
 */
class WelcomeSettings
{
    public const SETTING_KEY = 'welcome.aurora';

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'logo_reveal_speed' => 600,        // ms
            'tagline_reveal_delay' => 800,     // ms
            'animation_total_duration' => 3000,// ms
            'welcome_text' => 'Welcome to',
            'tagline_text' => "Let's get you started",
            'aurora_speed' => 8,               // seconds (blob loop)
            'brand_color_1' => '#0A6E6E',      // Deep Teal
            'brand_color_2' => '#D4A017',      // Warm Gold
        ];
    }

    public static function all(): array
    {
        $d = self::defaults();
        try {
            $stored = Setting::getValue(self::SETTING_KEY, []);
        } catch (\Throwable) {
            return $d;
        }

        return array_merge($d, is_array($stored) ? array_intersect_key($stored, $d) : []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function save(array $data): void
    {
        $merged = array_merge(self::all(), array_intersect_key($data, self::defaults()));
        Setting::setValue(self::SETTING_KEY, $merged, 'welcome');
    }

    public static function reset(): void
    {
        Setting::setValue(self::SETTING_KEY, self::defaults(), 'welcome');
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled', true);
    }
}
