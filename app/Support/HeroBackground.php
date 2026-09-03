<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Optional premium hero background for the customer dashboard (owner request).
 * The admin uploads a LIGHT and a DARK image (aurora/wave art, WebP or JPG);
 * the dashboard hero renders whichever matches the active theme, under a
 * gradient overlay so the heading/components still read cleanly. When nothing is
 * uploaded the hero keeps its default look — this is purely additive.
 *
 * Recommended asset: 1600×500 (16:5), WebP q75–80 (~60–140 KB). The hero band is
 * a fixed 16:5 aspect that `cover`s the image, so ONE asset stays crisp and
 * adds zero extra layout height on phone, tablet and desktop.
 */
class HeroBackground
{
    private const CACHE_KEY = 'dashboard.hero.v3';

    public const LIGHT_KEY = 'dashboard.hero.image_light';

    public const DARK_KEY = 'dashboard.hero.image_dark';

    /** Short line under the "My Connectivity" title (BUILD-13 §3). */
    public const DESC_KEY = 'dashboard.hero.description';

    /** Admin on/off switch for the dashboard hero image (owner request). */
    public const ENABLED_KEY = 'dashboard.hero.enabled';

    public const DEFAULT_DESCRIPTION = 'Your eSIMs, numbers, and wallet — all in one place.';

    /** @return array{light: ?string, dark: ?string, description: string, enabled: bool} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                $desc = trim((string) Setting::getValue(self::DESC_KEY, ''));

                return [
                    'light' => Setting::getValue(self::LIGHT_KEY) ?: null,
                    'dark' => Setting::getValue(self::DARK_KEY) ?: null,
                    'description' => $desc !== '' ? $desc : self::DEFAULT_DESCRIPTION,
                    // Default ON so existing installs are unchanged; the admin can
                    // turn the hero image off without removing the uploaded art.
                    'enabled' => (bool) Setting::getValue(self::ENABLED_KEY, true),
                ];
            } catch (\Throwable) {
                return ['light' => null, 'dark' => null, 'description' => self::DEFAULT_DESCRIPTION, 'enabled' => true];
            }
        });
    }

    public static function light(): ?string
    {
        return self::current()['light'];
    }

    public static function dark(): ?string
    {
        return self::current()['dark'];
    }

    /** The dashboard-home description line — never empty (falls back to default). */
    public static function description(): string
    {
        return self::current()['description'];
    }

    /** Admin toggle: is the dashboard hero image switched on? (Default true.) */
    public static function enabled(): bool
    {
        return self::current()['enabled'];
    }

    /** Whether at least one hero image is uploaded. */
    public static function isSet(): bool
    {
        $c = self::current();

        return filled($c['light']) || filled($c['dark']);
    }

    /** Whether the dashboard should actually SHOW the hero image right now:
     *  an image is uploaded AND the admin switch is on. */
    public static function showsOnDashboard(): bool
    {
        return self::isSet() && self::enabled();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isHeroKey(string $key): bool
    {
        return in_array($key, [self::LIGHT_KEY, self::DARK_KEY, self::DESC_KEY, self::ENABLED_KEY], true);
    }
}
