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
    private const CACHE_KEY = 'dashboard.hero.v1';

    public const LIGHT_KEY = 'dashboard.hero.image_light';

    public const DARK_KEY = 'dashboard.hero.image_dark';

    /** @return array{light: ?string, dark: ?string} */
    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'light' => Setting::getValue(self::LIGHT_KEY) ?: null,
                    'dark' => Setting::getValue(self::DARK_KEY) ?: null,
                ];
            } catch (\Throwable) {
                return ['light' => null, 'dark' => null];
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

    /** Whether at least one hero image is set (so the band should render). */
    public static function isSet(): bool
    {
        $c = self::current();

        return filled($c['light']) || filled($c['dark']);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isHeroKey(string $key): bool
    {
        return $key === self::LIGHT_KEY || $key === self::DARK_KEY;
    }
}
