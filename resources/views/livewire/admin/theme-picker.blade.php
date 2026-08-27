<div class="mx-auto max-w-5xl">
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Theme</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Pick one of the 15 skins for the whole platform. This changes colours, radius and typography
            for every user — the codebase, features and prices are untouched. It applies on the next page load.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($presets as $p)
            @php($t = $p['tokens']['colors'] ?? [])
            <div wire:key="theme-{{ $p['slug'] }}"
                 @class([
                    'relative flex flex-col overflow-hidden rounded-2xl border bg-white p-4 transition dark:bg-[#1A2840]',
                    'border-primary ring-2 ring-primary/40 dark:border-teal-400' => $p['slug'] === $active,
                    'border-slate-200 dark:border-[#2D4060]' => $p['slug'] !== $active,
                 ])>
                {{-- Swatch strip — the preset's core palette. --}}
                <div class="mb-3 flex h-14 overflow-hidden rounded-xl ring-1 ring-black/5 dark:ring-white/10">
                    @foreach (['primary', 'primary_dark', 'accent', 'navy', 'action'] as $key)
                        @if (! empty($t[$key]))
                            <span class="flex-1" style="background: rgb({{ $t[$key] }})" title="{{ $key }}"></span>
                        @endif
                    @endforeach
                </div>

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $p['name'] }}</h2>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $p['persona'] }}</p>
                    </div>
                    @if ($p['is_built_in'])
                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-white/10 dark:text-slate-300">Built-in</span>
                    @endif
                </div>

                <div class="mt-3 flex items-center gap-2">
                    @if ($p['slug'] === $active)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary/10 px-3 py-2 text-sm font-semibold text-primary dark:bg-teal-500/15 dark:text-teal-300">
                            <x-icon name="check" class="h-4 w-4" /> Active
                        </span>
                    @else
                        <button type="button" wire:click="apply('{{ $p['slug'] }}')"
                                wire:confirm="Apply “{{ $p['name'] }}” to the live platform? Every logged-in user will see it on their next page load."
                                wire:loading.attr="disabled" wire:target="apply('{{ $p['slug'] }}')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <x-icon name="check" class="h-4 w-4" wire:loading.remove wire:target="apply('{{ $p['slug'] }}')" />
                            <svg wire:loading wire:target="apply('{{ $p['slug'] }}')" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            Apply
                        </button>
                    @endif
                    <span class="text-[11px] uppercase tracking-wide text-slate-400">{{ str_replace('_', ' ', $p['icon_family']['style'] ?? 'sprite') }} icons</span>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-5 text-xs text-slate-400 dark:text-slate-500">
        Structural layout variants and the per-theme hero art land in the next batch — today each theme
        recolours and re-shapes the whole platform. “Naara Official” is the permanent default and can’t be removed.
    </p>
</div>
