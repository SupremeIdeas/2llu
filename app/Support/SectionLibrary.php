<?php

namespace App\Support;

/**
 * The section-type registry for the universal Section Builder (Section Builder
 * prompt §2). Each type declares its label, icon, blade partial and its config
 * DEFAULTS (the config blob is stored as JSON per section and validated per-type
 * in the admin builder). Keeping the registry in one place means the admin UI,
 * the renderer and the validator all agree on what a type is and what it holds.
 *
 * Foundation increment ships: Hero (the flagship — 5 presets × 3 background
 * modes), Two-column (image + text), and Custom HTML (allowlist-sanitized). The
 * remaining library types (bento, carousel, testimonial, FAQ, logo strip,
 * stacking, video, code) plug into the same registry in later increments.
 */
class SectionLibrary
{
    /**
     * @return array<string, array{label:string, icon:string, blade:string, description:string, defaults:array<string,mixed>}>
     */
    public static function types(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
                'icon' => 'zap',
                'blade' => 'partials.sections.hero',
                'description' => 'Large opening banner — animated gradient, background image, image slideshow or a clean static layout.',
                'defaults' => self::applyHeroPreset(self::heroBase(), 'aurora'),
            ],
            'two_column' => [
                'label' => 'Two-column',
                'icon' => 'list',
                'blade' => 'partials.sections.two-column',
                'description' => 'Image on one side, copy on the other — admin picks which side the image sits.',
                'defaults' => [
                    'eyebrow' => '',
                    'headline' => 'A headline that sells the point',
                    'body' => 'A short supporting paragraph. Keep it tight — one idea, clearly stated.',
                    'image' => '',
                    'image_side' => 'right',      // left | right
                    'cta_label' => '',
                    'cta_target' => '',
                    'bg' => 'transparent',        // transparent | tint | dark
                ],
            ],
            'custom_html' => [
                'label' => 'Custom HTML',
                'icon' => 'hash',
                'blade' => 'partials.sections.custom-html',
                'description' => 'Paste raw HTML for one-off needs. Sanitized against XSS on save (allowlist, not raw-render).',
                'defaults' => [
                    'html' => '',
                    'max_width' => 'container',   // container | full
                ],
            ],
        ];
    }

    /** The 5 pre-made, reusable 2026-trending hero looks (prompt: "build like 5 premade hero sections"). */
    public static function heroPresets(): array
    {
        return [
            'aurora' => [
                'label' => 'Aurora',
                'description' => 'Animated teal→gold→coral gradient drift, centered copy. The flagship look.',
            ],
            'spotlight' => [
                'label' => 'Spotlight',
                'description' => 'Deep navy with a radial glow behind bold, centered headline text.',
            ],
            'split' => [
                'label' => 'Split',
                'description' => 'Copy on the left, a product image on the right. Great for a feature launch.',
            ],
            'minimal' => [
                'label' => 'Minimal',
                'description' => 'Clean, light, generous whitespace. Left-aligned, understated, fast.',
            ],
            'showcase' => [
                'label' => 'Showcase',
                'description' => 'Full-bleed image slideshow with a bottom scrim and overlaid copy.',
            ],
        ];
    }

    /** The style/mode defaults each preset applies on top of the shared hero base. */
    private static function presetOverrides(string $preset): array
    {
        return match ($preset) {
            'spotlight' => ['mode' => 'animation', 'align' => 'center', 'scheme' => 'dark'],
            'split' => ['mode' => 'image', 'align' => 'left', 'scheme' => 'light'],
            'minimal' => ['mode' => 'static', 'align' => 'left', 'scheme' => 'light'],
            'showcase' => ['mode' => 'images', 'align' => 'left', 'scheme' => 'dark'],
            default => ['mode' => 'animation', 'align' => 'center', 'scheme' => 'brand'], // aurora
        };
    }

    /** Shared hero config skeleton before a preset is layered on. */
    private static function heroBase(): array
    {
        return [
            'preset' => 'aurora',
            'mode' => 'animation',              // animation | image | images | static
            'scheme' => 'brand',                // brand | light | dark
            'align' => 'center',                // left | center
            'eyebrow' => 'Stay Connected. No Borders.',
            'headline' => 'Land Anywhere. Connect Instantly.',
            'subheadline' => 'A local data plan and a real second number in 190+ countries — activated before you even leave home.',
            'cta_primary_label' => 'Get Started',
            'cta_primary_target' => '',
            'cta_secondary_label' => 'See How It Works',
            'cta_secondary_target' => '',
            'images' => [],                     // urls; [0] used as bg for 'image' mode
        ];
    }

    /** Merge a preset's look onto a hero config (used for defaults + when the admin switches preset). */
    public static function applyHeroPreset(array $config, string $preset): array
    {
        $preset = array_key_exists($preset, self::heroPresets()) ? $preset : 'aurora';

        return array_merge($config, self::presetOverrides($preset), ['preset' => $preset]);
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    public static function defaultsFor(string $type): array
    {
        return self::types()[$type]['defaults'] ?? [];
    }

    public static function bladeFor(string $type): ?string
    {
        return self::types()[$type]['blade'] ?? null;
    }

    public static function labelFor(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
