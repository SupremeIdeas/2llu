<div class="mx-auto max-w-3xl" x-data="{ term: '' }" @search.window="term = $event.detail.term">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">UI Kit</h1>
    <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">The reusable, themed, accessible components (Section 31). Everything here is keyboard-usable and dark-mode ready.</p>

    <div class="space-y-6">
        {{-- Star rating --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Star rating</h2>
            <div class="flex flex-wrap items-center gap-6">
                <div>
                    <p class="mb-1 text-xs text-slate-400">Display (4.5)</p>
                    <x-ui.star-rating :value="4.5" />
                </div>
                <div>
                    <p class="mb-1 text-xs text-slate-400">Interactive (click / arrow keys)</p>
                    <x-ui.star-rating :value="$demoRating" :readonly="false" wire="demoRating" size="h-7 w-7" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You picked: <span class="font-semibold">{{ $demoRating }}</span></p>
                </div>
            </div>
        </section>

        {{-- Modal engine --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Modal engine (the only one)</h2>
            <button type="button" x-data
                    @click="$dispatch('open-modal', { name: 'demo' })"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="help-circle" class="h-4 w-4" /> Open dialog
            </button>
            <p class="mt-2 text-xs text-slate-400">Focus is trapped inside; ESC or backdrop closes it.</p>

            <x-ui.modal name="demo" title="Demo dialog" max-width="md">
                <p class="text-sm text-slate-600 dark:text-slate-300">This dialog uses the shared engine. Tab cycles only these controls; ESC closes.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" x-data @click="$dispatch('close-modal')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-[#2D4060] dark:text-slate-200">Cancel</button>
                    <button type="button" x-data @click="$dispatch('close-modal')" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white">Confirm</button>
                </div>
            </x-ui.modal>
        </section>

        {{-- Countdown --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Server-anchored countdown</h2>
            <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                <x-ui.countdown :until="$countdownUntil" />
            </div>
            <p class="mt-2 text-xs text-slate-400">Anchored to server time — a wrong device clock can’t speed it up or slow it down.</p>
        </section>

        {{-- Search --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Search (debounced)</h2>
            <x-ui.search placeholder="Type to search…" class="max-w-sm" />
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Debounced term: <span class="font-mono" x-text="term || '—'"></span></p>
        </section>

        {{-- Theme toggle --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Theme toggle</h2>
            <div class="flex items-center gap-3">
                <x-theme-toggle />
                <span class="text-sm text-slate-500 dark:text-slate-400">Flips light / dark with no flash.</span>
            </div>
        </section>
    </div>
</div>
