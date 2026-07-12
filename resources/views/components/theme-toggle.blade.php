{{--
    Theme toggle (blueprint Section 4.3). Inline SVG only — no emoji.
    The full <x-icon> sprite arrives in Module 8; for the Foundation blank
    layout the moon/sun marks are inlined here so the toggle is self-contained.
    State is persisted in localStorage and applied to <html> via Alpine, and a
    `theme-changed` event is dispatched for any listener (charts, GSAP) later.
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
    <svg x-show="!dark" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
         viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
    </svg>
    {{-- sun: shown in dark mode (click -> go light) --}}
    <svg x-show="dark" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
         viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
    </svg>
</button>
