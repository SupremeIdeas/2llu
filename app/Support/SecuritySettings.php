<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-toggleable runtime security controls (blueprint Section 30). The two
 * headers most likely to clash with an unknown self-hosted environment — the
 * Content-Security-Policy and HSTS (force-HTTPS) — can be switched on/off from
 * the admin panel with no redeploy. Everything defaults to ON (secure); the
 * admin only turns one off if it visibly breaks something on their host.
 *
 * Values are cached and busted on any `security.*` setting save. Reads degrade
 * to the config defaults if settings/DB are unavailable (e.g. pre-install), so
 * a hardened default is always applied.
 */
class SecuritySettings
{
    private const CACHE_KEY = 'security.settings';

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'csp_enabled' => self::boolSetting('security.csp_enabled', (bool) config('security.csp.enabled', true)),
                    'hsts_enabled' => self::boolSetting('security.hsts_enabled', (bool) config('security.hsts.enabled', true)),
                ];
            } catch (\Throwable) {
                return [
                    'csp_enabled' => (bool) config('security.csp.enabled', true),
                    'hsts_enabled' => (bool) config('security.hsts.enabled', true),
                ];
            }
        });
    }

    public static function cspEnabled(): bool
    {
        return self::current()['csp_enabled'];
    }

    public static function hstsEnabled(): bool
    {
        return self::current()['hsts_enabled'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isSecurityKey(string $key): bool
    {
        return str_starts_with($key, 'security.');
    }

    private static function boolSetting(string $key, bool $default): bool
    {
        $value = Setting::getValue($key);

        return $value === null ? $default : (bool) $value;
    }
}
