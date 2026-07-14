<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-configurable persona + knowledge for the NaaraCare agent (Module 24).
 * The operator sets the agent's human name, tone/persona, and extra
 * platform-specific knowledge from the admin panel; the agent grounds every
 * answer on it. Cached; busted on any `support.*` setting save.
 */
class SupportSettings
{
    private const CACHE_KEY = 'support.settings';

    public const DEFAULT_NAME = 'Nia';

    public const DEFAULT_PERSONA = "You are warm, calm and genuinely helpful, like a friendly human support specialist. You use the customer's name when known, keep replies concise, and never sound robotic.";

    public static function current(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return [
                    'name' => Setting::getValue('support.agent_name') ?: self::DEFAULT_NAME,
                    'persona' => Setting::getValue('support.persona') ?: self::DEFAULT_PERSONA,
                    'knowledge' => Setting::getValue('support.knowledge') ?: '',
                ];
            } catch (\Throwable) {
                return ['name' => self::DEFAULT_NAME, 'persona' => self::DEFAULT_PERSONA, 'knowledge' => ''];
            }
        });
    }

    public static function name(): string
    {
        return self::current()['name'];
    }

    public static function persona(): string
    {
        return self::current()['persona'];
    }

    public static function knowledge(): string
    {
        return self::current()['knowledge'];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isSupportKey(string $key): bool
    {
        return str_starts_with($key, 'support.');
    }
}
