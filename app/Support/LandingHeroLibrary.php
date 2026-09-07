<?php

namespace App\Support;

/**
 * The registry behind per-theme custom landing pages (owner request,
 * 2026-09-07). Mirrors SectionLibrary's own registry pattern (type => blade
 * + defaults) but scoped to `ThemePreset::sectionStyle('landing_hero')`
 * instead of the generic page-builder: each entry is a THEME-SPECIFIC,
 * hand-built landing page (structurally mimicking a real reference layout,
 * never a generic template) plus the small set of admin-editable content
 * fields that specific layout actually exposes.
 *
 * This is deliberately additive to — not a replacement for — the existing
 * SiteContent / PageBuilder homepage content systems. 'default' (i.e. no
 * entry here) means a theme keeps using those exactly as today. Only a
 * theme with a genuinely unique, custom-coded landing layout gets an entry.
 *
 * "we seed the extra controls as we are building... adopting the editor to
 * also learn for future tweak and extending layout capabilities" — this
 * registry IS that adoption point: `Admin\ThemePicker`'s landing-content
 * editor is entirely schema-driven off `fields()` below, so shipping a new
 * landing style for a future theme batch is just adding one array here —
 * the admin editor automatically grows a matching form, no UI code change.
 */
class LandingHeroLibrary
{
    public const IMAGE_RADIUS_OPTIONS = ['none' => 'None', 'md' => 'Rounded', 'xl' => 'Very rounded', 'full' => 'Circular'];

    public const IMAGE_POSITION_OPTIONS = ['left' => 'Left', 'right' => 'Right', 'center' => 'Center'];

    /**
     * @return array<string, array{blade: string, fields: array<int, array<string, mixed>>}>
     */
    public static function styles(): array
    {
        return [
            'neon-vertex' => [
                'blade' => 'marketing.theme-landing.neon-vertex',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'New · Naara 2.0'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Connect. Roam. Disrupt distance.'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'The all-in-one app to get data and a real number in 190+ countries, faster than ever.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Start roaming'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => null],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'xl'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'right'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '190+'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Countries covered'],
                ],
            ],
            'midnight-signal' => [
                'blade' => 'marketing.theme-landing.midnight-signal',
                'fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow tag', 'max' => 40, 'default' => 'Fly smarter with Naara'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline', 'max' => 80, 'default' => 'Stay connected on every trip'],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'Description', 'max' => 220, 'default' => 'Get the cheapest local data and a real number, the moment you land — no roaming shock, no SIM swap.'],
                    ['key' => 'cta_label', 'type' => 'text', 'label' => 'Primary button label', 'max' => 30, 'default' => 'Search plans now'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'Feature image', 'default' => null],
                    ['key' => 'image_radius', 'type' => 'select', 'label' => 'Image corner style', 'options' => self::IMAGE_RADIUS_OPTIONS, 'default' => 'md'],
                    ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => self::IMAGE_POSITION_OPTIONS, 'default' => 'center'],
                    ['key' => 'stat_value', 'type' => 'text', 'label' => 'Stat value', 'max' => 12, 'default' => '94%'],
                    ['key' => 'stat_label', 'type' => 'text', 'label' => 'Stat label', 'max' => 40, 'default' => 'Coverage predicted before you land'],
                ],
            ],
        ];
    }

    public static function has(string $style): bool
    {
        return array_key_exists($style, self::styles());
    }

    /** @return array<int, array<string, mixed>> */
    public static function fieldsFor(string $style): array
    {
        return self::styles()[$style]['fields'] ?? [];
    }

    public static function bladeFor(string $style): ?string
    {
        return self::styles()[$style]['blade'] ?? null;
    }

    /** Every field's default value, keyed by field key — the content shown before an admin edits anything. */
    public static function defaultsFor(string $style): array
    {
        return collect(self::fieldsFor($style))->mapWithKeys(fn ($f) => [$f['key'] => $f['default'] ?? null])->all();
    }
}
