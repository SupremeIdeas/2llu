<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
        <x-icon name="settings" class="h-6 w-6 text-butter" /> Fee Settings
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Global fee percentages/flats used by the circle contribution and payout engine. Each value is bounded — a save outside the allowed range is rejected, never silently clamped.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif
    @if ($error)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            <x-icon name="info" class="h-4 w-4 shrink-0" /> {{ $error }}
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($settings as $setting)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-[220px]">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ str($setting->key)->headline() }}</p>
                        <p class="font-brand-mono tabular-nums text-xs text-slate-400 dark:text-slate-500">range: {{ $setting->min_value }} – {{ $setting->max_value }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            wire:model="values.{{ $setting->key }}"
                            type="number" step="0.0001"
                            min="{{ $setting->min_value }}" max="{{ $setting->max_value }}"
                            class="w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-brand-mono text-sm tabular-nums text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"
                        >
                        <button type="button" wire:click="save('{{ $setting->key }}')"
                                class="rounded-lg bg-butter px-4 py-2 text-sm font-semibold text-espresso hover:brightness-95">
                            Save
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
