<?php

namespace App\Support;

use App\Models\NavSlot;
use Illuminate\Support\Facades\Cache;

/**
 * Read/seed helper for the floating navigation bar (Homepage floating-nav §2).
 * Serves the visible, ordered slots for the current auth state and seeds a
 * sensible default set on first use. Cached; flushed when the admin edits.
 */
class NavSlots
{
    private const CACHE_KEY = 'nav.slots.v1';

    /** The default floating-bar set (prompt: Home, eSIMs, Numbers, Account, About + Wizard centre). */
    public static function defaults(): array
    {
        return [
            ['position' => 1, 'label' => 'Home', 'icon' => 'globe', 'target' => 'home', 'visibility' => 'all', 'is_center' => false],
            ['position' => 2, 'label' => 'Plans', 'icon' => 'wifi', 'target' => 'pricing', 'visibility' => 'all', 'is_center' => false],
            ['position' => 0, 'label' => 'Ask NaaraSim', 'icon' => 'message-circle', 'target' => NavSlot::TARGET_WIZARD, 'visibility' => 'all', 'is_center' => true],
            ['position' => 3, 'label' => 'Numbers', 'icon' => 'phone', 'target' => 'pricing', 'visibility' => 'all', 'is_center' => false],
            ['position' => 4, 'label' => 'About', 'icon' => 'info', 'target' => 'about', 'visibility' => 'all', 'is_center' => false],
            ['position' => 5, 'label' => 'Account', 'icon' => 'id-card', 'target' => 'dashboard', 'visibility' => 'auth', 'is_center' => false],
            ['position' => 5, 'label' => 'Get started', 'icon' => 'id-card', 'target' => 'register', 'visibility' => 'guest', 'is_center' => false],
        ];
    }

    /** Seed the default set once (called lazily on first read; also usable by admin). */
    public static function ensureSeeded(): void
    {
        if (NavSlot::count() === 0) {
            foreach (self::defaults() as $slot) {
                NavSlot::create($slot + ['is_active' => true]);
            }
            self::flush();
        }
    }

    /** @return \Illuminate\Support\Collection<int, NavSlot> */
    public static function all()
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                self::ensureSeeded();

                return NavSlot::orderBy('position')->orderBy('id')->get();
            });
        } catch (\Throwable) {
            // Table not migrated yet (e.g. a bare test env) — degrade to no bar.
            return collect();
        }
    }

    /**
     * The bar split for rendering: the centerpiece plus the regular slots that
     * apply to this auth state, split evenly left/right of the centre.
     *
     * @return array{center: ?NavSlot, left: array, right: array}
     */
    public static function bar(bool $authed): array
    {
        $active = self::all()->where('is_active', true)->filter(fn (NavSlot $s) => $s->visibleTo($authed));
        $center = $active->firstWhere('is_center', true);
        $regular = $active->where('is_center', false)->values();

        $half = (int) ceil($regular->count() / 2);

        return [
            'center' => $center,
            'left' => $regular->slice(0, $half)->values()->all(),
            'right' => $regular->slice($half)->values()->all(),
        ];
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
