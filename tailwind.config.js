import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // NaaraSim UI rule (CLAUDE.md): class-based dark mode, toggled on <html>.
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Body copy (Module 26).
                sans: ['Didact Gothic', 'Figtree', ...defaultTheme.fontFamily.sans],
                // Titles & headings — the Supreme Ideas Agency custom font.
                display: ['Supreme Display', 'Figtree', ...defaultTheme.fontFamily.sans],
                // 2LLU design-system type (Batch 1 Step 3 — brand-bible.md /
                // docs/blueprints/01-batch1-clone-strip-foundation.md). Namespaced
                // under `brand-*` rather than the bare `display`/`body`/`mono` keys
                // the blueprint snippet shows: `display` above is still the live,
                // site-wide NaaraSim heading font (driven by --font-display in
                // resources/css/app.css) and `mono` isn't set here at all today,
                // which means `font-mono` currently resolves to Tailwind's default
                // monospace stack and is already used in 60+ views — including
                // esim-compatibility.blade.php and the 40-theme-preset pages, both
                // explicitly off-limits for this pass. Overwriting either key would
                // silently reflow live, unrelated pages instead of staying additive.
                // When a later batch actually rebrands a page, either reference
                // `font-brand-display`/`font-brand-body`/`font-brand-mono` directly
                // or rename these keys to the bare names at that time (mechanical).
                'brand-display': ['Fraunces', 'serif'],
                'brand-body': ['Sora', 'sans-serif'],
                'brand-mono': ['"IBM Plex Mono"', 'monospace'],
            },
            // Brand palette (blueprint Section 2.1 / 4.1). Driven by CSS variables
            // so the admin can recolour the whole platform at runtime with NO
            // rebuild (Module 26 — defaults live in resources/css/app.css and an
            // admin override <style> is injected in the layout head). The
            // channel-triple form keeps Tailwind's /opacity utilities working.
            colors: {
                primary: {
                    DEFAULT: 'rgb(var(--brand-primary) / <alpha-value>)', // Deep Teal
                    dark: 'rgb(var(--brand-primary-dark) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--brand-accent) / <alpha-value>)', // Warm Gold
                    dark: 'rgb(var(--brand-accent-dark) / <alpha-value>)', // text/icon-safe on light surfaces
                },
                navy: 'rgb(var(--brand-navy) / <alpha-value>)',       // Midnight Navy
                action: 'rgb(var(--brand-action) / <alpha-value>)',   // Coral Red
                success: '#16A34A',
                warning: '#D97706',
                danger: '#DC2626',

                // 2LLU design-system palette (Batch 1 Step 3). Plain hex, not the
                // CSS-var-driven admin-recolour pattern above — these are the fixed
                // "coffee & milk" brand tokens (brand-bible.md), not a runtime-
                // themeable surface. Additive: no existing key names touched.
                espresso: { DEFAULT: '#1B120B', surface: '#271A11', border: '#4A3220' },
                milk: { DEFAULT: '#FBF7EE', surface: '#F3EAD9', border: '#E2D0AF' },
                butter: '#E8B34C',
                caramel: '#B97A3E',
                coffee: '#7A4A25',
            },
            // "The Pour" signature gradient (Batch 1 Step 3): soft mesh gradient,
            // butter → caramel → base, evoking milk poured into coffee. Landing
            // hero treatment — `bg-pour-light dark:bg-pour-dark`. See the
            // <x-brand.pour-gradient> component for a drop-in wrapper.
            backgroundImage: {
                'pour-dark': 'radial-gradient(at 20% 15%, #E8B34C33 0, transparent 45%), radial-gradient(at 60% 40%, #B97A3E40 0, transparent 55%), radial-gradient(at 85% 85%, #1B120B 0, transparent 70%)',
                'pour-light': 'radial-gradient(at 20% 15%, #E8B34C40 0, transparent 45%), radial-gradient(at 60% 40%, #7A4A2522 0, transparent 55%), radial-gradient(at 85% 85%, #FBF7EE 0, transparent 70%)',
            },
        },
    },
    plugins: [],
};
