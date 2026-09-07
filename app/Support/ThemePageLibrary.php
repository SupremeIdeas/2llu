<?php

namespace App\Support;

/**
 * The registry behind a theme's full page suite beyond the homepage (owner
 * request, 2026-09-07): "for each theme, will and must carry its own
 * homepage, about us page, and 3 extra important page layouts." Mirrors
 * LandingHeroLibrary's exact pattern (page => style => blade + fields) but
 * generalized across MULTIPLE pages instead of just the landing hero, so
 * adding a 4th, 5th, 6th themed page later is one more top-level key here —
 * no new PHP class, no admin-UI code change (`Admin\ThemePicker::editPage()`
 * is entirely schema-driven off this registry, same as the landing editor).
 *
 * Pricing is deliberately absent: it's a real Livewire component with live
 * pricing logic (`Admin\PricingPage`), not a content page, so forking its
 * whole layout per theme is a materially bigger, riskier change than a
 * content-page reskin — a decision, not an oversight.
 */
class ThemePageLibrary
{
    public const PAGES = ['about_page', 'how_it_works_page', 'contact_page'];

    /**
     * @return array<string, array<string, array{blade: string, fields: array<int, array<string, mixed>>}>>
     */
    public static function registry(): array
    {
        return [
            'about_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Our story'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Built by travellers, for travellers'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'NaaraSim started with a simple frustration: landing in a new country and losing signal at exactly the wrong moment. We built the fix — one app for data and numbers, everywhere.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'To make borders irrelevant to how connected you feel — one wallet, one app, 190+ countries.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A world where the first thing that happens off a plane is never a signal search.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank founded Supreme Ideas Agency in Onitsha to close the gap he kept hitting on his own trips across Africa: data that ran out mid-journey and numbers that didn\'t survive a border crossing. NaaraSim is the fix, built for every traveller after him.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.about',
                    'fields' => [
                        ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Signal, decoded'],
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'We read the network so you don\'t have to'],
                        ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro paragraph', 'max' => 320, 'default' => 'Every carrier, every country, every fine-print roaming rule — we track it all in real time so your connection just works, the moment you land.'],
                        ['key' => 'mission', 'type' => 'textarea', 'label' => 'Mission statement', 'max' => 260, 'default' => 'Predictable connectivity, everywhere — no surprise bills, no dead zones, no second-guessing.'],
                        ['key' => 'vision', 'type' => 'textarea', 'label' => 'Vision statement', 'max' => 260, 'default' => 'A single signal room watching every network on Earth, so your phone never has to guess.'],
                        ['key' => 'founder_name', 'type' => 'text', 'label' => 'Founder name', 'max' => 60, 'default' => 'Frank Charles Ebubedike'],
                        ['key' => 'founder_title', 'type' => 'text', 'label' => 'Founder title', 'max' => 60, 'default' => 'Founder, Supreme Ideas Agency'],
                        ['key' => 'founder_bio', 'type' => 'textarea', 'label' => 'Founder bio', 'max' => 320, 'default' => 'Frank built the first version of this "signal room" for himself, tracking carrier quality across his own routes through Africa by hand. NaaraSim turns that same discipline into a product every traveller can lean on.'],
                    ],
                ],
            ],
            'how_it_works_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'From download to connected in 4 steps'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'No store visit, no waiting on a courier — everything happens on your phone.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.how-it-works',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'The signal path, step by step'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'From the moment you buy to the moment you land, here\'s exactly what happens.'],
                    ],
                ],
            ],
            'contact_page' => [
                'neon-vertex' => [
                    'blade' => 'marketing.theme-pages.neon-vertex.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Let\'s get you connected'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Questions about a plan, a number, or your wallet? We reply fast.'],
                    ],
                ],
                'midnight-signal' => [
                    'blade' => 'marketing.theme-pages.midnight-signal.contact',
                    'fields' => [
                        ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Talk to the signal room'],
                        ['key' => 'subtext', 'type' => 'textarea', 'label' => 'Subtext', 'max' => 200, 'default' => 'Real humans, real answers — usually within the hour.'],
                    ],
                ],
            ],
        ];
    }

    public static function has(string $page, string $style): bool
    {
        return isset(self::registry()[$page][$style]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function fieldsFor(string $page, string $style): array
    {
        return self::registry()[$page][$style]['fields'] ?? [];
    }

    public static function bladeFor(string $page, string $style): ?string
    {
        return self::registry()[$page][$style]['blade'] ?? null;
    }

    public static function defaultsFor(string $page, string $style): array
    {
        return collect(self::fieldsFor($page, $style))->mapWithKeys(fn ($f) => [$f['key'] => $f['default'] ?? null])->all();
    }
}
