<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed social media links for the site footer (owner request). Each
 * platform's icon shows ONLY when its URL is saved — so the row stays hidden
 * until the operator's accounts actually exist (no dead links). Stored as one
 * setting map, cached for an hour.
 */
class SocialLinks
{
    public const SETTING_KEY = 'site.social_links';

    /** platform => [label, service-icon slug]. */
    public const PLATFORMS = [
        'x' => ['X (Twitter)', 'x'],
        'facebook' => ['Facebook', 'facebook'],
        'instagram' => ['Instagram', 'instagram'],
        'tiktok' => ['TikTok', 'tiktok'],
        'linkedin' => ['LinkedIn', 'linkedin'],
        'youtube' => ['YouTube', 'youtube'],
        'discord' => ['Discord', 'discord'],
        'whatsapp' => ['WhatsApp', 'whatsapp'],
    ];

    public static function isCacheKey(string $key): bool
    {
        return $key === self::SETTING_KEY;
    }

    /** The saved platform => url map (raw). */
    public static function all(): array
    {
        try {
            return Cache::remember(self::SETTING_KEY, 3600, function () {
                $raw = Setting::getValue(self::SETTING_KEY, []);

                return is_array($raw) ? $raw : [];
            });
        } catch (\Throwable $e) {
            // Settings unavailable (pre-install / DB hiccup) — no links rather
            // than a broken page.
            return [];
        }
    }

    /** Save the links (only valid URLs are kept). */
    public static function save(array $urls): void
    {
        $map = [];
        foreach (self::PLATFORMS as $key => $_) {
            $url = trim((string) ($urls[$key] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $map[$key] = $url;
            }
        }
        Setting::setValue(self::SETTING_KEY, $map, 'site');
        Cache::forget(self::SETTING_KEY);
    }

    /**
     * Configured links for rendering: [['platform','label','icon','url'], …].
     * Empty when nothing is set — the footer row then hides entirely.
     */
    public static function forFooter(): array
    {
        $saved = self::all();
        $out = [];
        foreach (self::PLATFORMS as $key => [$label, $icon]) {
            if (! empty($saved[$key])) {
                $out[] = ['platform' => $key, 'label' => $label, 'icon' => $icon, 'url' => $saved[$key]];
            }
        }

        return $out;
    }

    public static function any(): bool
    {
        return self::forFooter() !== [];
    }
}
