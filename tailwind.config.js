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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Brand palette (blueprint Section 2.1 / 4.1). Use tokens, never raw hex.
            colors: {
                primary: {
                    DEFAULT: '#0A6E6E', // Deep Teal
                    dark: '#085555',    // Teal Dark
                },
                accent: '#D4A017',      // Warm Gold
                navy: '#0D1B2A',        // Midnight Navy
                action: '#E8412A',      // Coral Red
                success: '#16A34A',
                warning: '#D97706',
                danger: '#DC2626',
            },
        },
    },
    plugins: [],
};
