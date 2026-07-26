<div>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">eSIM Data Plans</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">190+ countries. Stay connected. No borders. No swaps.</p>
        </div>
    </div>

    <div class="relative mb-6 max-w-md">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
            <x-icon name="search" class="h-4 w-4" />
        </span>
        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Search plans by name…"
               class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-slate-100">
        <span wire:loading wire:target="search" class="absolute right-3 top-1/2 -translate-y-1/2 text-primary">
            <x-ui.spinner class="h-4 w-4" />
        </span>
    </div>

    <div wire:loading.class="opacity-50" class="grid grid-cols-1 gap-4 transition-opacity sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($plans as $plan)
            <div wire:key="plan-{{ $plan->id }}"
                 class="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="mb-3 flex items-start justify-between">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                        <x-icon name="globe" class="h-3.5 w-3.5" />
                        {{ $plan->type ?? 'Data' }}
                    </span>
                    @if ($plan->is_featured)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-accent">
                            <x-icon name="zap" class="h-3.5 w-3.5" /> Featured
                        </span>
                    @endif
                </div>

                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $plan->name }}</h3>
                <div class="mt-2 flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1"><x-icon name="signal" class="h-4 w-4" />
                        {{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB' : 'Unlimited' }}</span>
                    <span class="inline-flex items-center gap-1"><x-icon name="refresh" class="h-4 w-4" />
                        {{ $plan->validity_days ? $plan->validity_days.' days' : '—' }}</span>
                </div>

                <div class="mt-4 flex items-end justify-between border-t border-slate-100 pt-4 dark:border-[#243352]">
                    <div>
                        {{-- Localized price (owner request): USD default + the
                             viewer's local-currency equivalent (live FX). --}}
                        @php($__loc = app(\App\Services\Pricing\CurrencyService::class)->localPrice((float) $plan->final_retail_usd, \App\Support\LocaleCurrency::resolve(auth()->user())))
                        <div class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $__loc['usd'] }}</div>
                        @if ($__loc['local'])
                            <div class="text-xs text-slate-500 dark:text-slate-400">≈ {{ $__loc['local'] }}</div>
                        @endif
                    </div>
                    <a href="{{ route('checkout', $plan) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/50">
                        Buy <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-[#2D4060] dark:text-slate-400">
                <x-icon name="package" class="mx-auto mb-2 h-8 w-8" />
                No plans found. Try a different search.
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $plans->links() }}</div>
</div>
