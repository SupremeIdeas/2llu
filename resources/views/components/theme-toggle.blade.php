{{--
    Theme toggle (blueprint Section 4.3). SVG icons only — no emoji. Uses the
    shared <x-icon> sprite (Module 8). State is persisted in localStorage and
    applied to <html> via Alpine; a `theme-changed` event is dispatched for any
    listener (charts, GSAP) later.
--}}
<button type="button"
        x-data="{ dark: document.documentElement.classList.contains('dark') }"
        @click="dark = !dark;
                localStorage.setItem('theme', dark ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', dark);
                $dispatch('theme-changed', { dark })"
        :aria-pressed="dark.toString()"
        aria-label="Toggle dark mode"
        class="inline-flex items-center justify-center p-2 rounded-lg border border-slate-300 bg-white text-primary transition-colors duration-300 hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-accent dark:hover:bg-primary/20">
    {{-- moon: shown in light mode (click -> go dark) --}}
    <span x-show="!dark" x-cloak><x-icon name="moon" class="w-5 h-5" /></span>
    {{-- sun: shown in dark mode (click -> go light) --}}
    <span x-show="dark" x-cloak><x-icon name="sun" class="w-5 h-5" /></span>
</button>
