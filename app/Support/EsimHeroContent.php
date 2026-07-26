<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * eSIM storefront hero content (esim_upgrade Part 2). Admin-managed title,
 * description, and up to FOUR ordered images for the interchanging-reveal hero.
 * Setting-backed + cached; falls back to the shipped seed images so the hero
 * always looks complete before the admin uploads their own.
 */
class EsimHeroContent
{
    private const CACHE = 'esim.hero.v1';

    public const MAX_IMAGES = 4;

    public const TITLE_KEY = 'esim.hero.title';

    public const DESC_KEY = 'esim.hero.description';

    public const IMAGES_KEY = 'esim.hero.images';

    /** Shipped defaults (extracted seed assets under /public/img/esim). */
    private const DEFAULT_IMAGES = [
        '/img/esim/hero-1.webp',
        '/img/esim/hero-2.webp',
        '/img/esim/hero-3.webp',
        '/img/esim/hero-4.webp',
    ];

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            try {
                $images = Setting::getValue(self::IMAGES_KEY);
                $images = is_array($images) && $images !== [] ? array_values($images) : self::DEFAULT_IMAGES;

                return [
                    'title' => Setting::getValue(self::TITLE_KEY) ?: 'Data that follows you. No borders. No swaps.',
                    'description' => Setting::getValue(self::DESC_KEY) ?: 'Instant eSIM data for 190+ countries — installed in minutes, right from your phone.',
                    'images' => array_slice($images, 0, self::MAX_IMAGES),
                ];
            } catch (\Throwable) {
                return [
                    'title' => 'Data that follows you. No borders. No swaps.',
                    'description' => 'Instant eSIM data for 190+ countries — installed in minutes, right from your phone.',
                    'images' => self::DEFAULT_IMAGES,
                ];
            }
        });
    }

    public static function title(): string
    {
        return self::current()['title'];
    }

    public static function description(): string
    {
        return self::current()['description'];
    }

    /** @return list<string> ordered image URLs (1–4). */
    public static function images(): array
    {
        return self::current()['images'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    public static function isHeroKey(string $key): bool
    {
        return str_starts_with($key, 'esim.hero.');
    }
}
