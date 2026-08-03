{{-- One plan card. Tapping the card opens the dedicated plan-detail view
     (BUILD-8 §3.4); the AI tooltip (§5) is a tap-to-reveal icon that never
     navigates. Expects: $plan, $fmt. --}}
@php($price = $fmt((float) $plan->final_retail_usd))
<div wire:key="plan-{{ $plan->id }}" wire:click="openPlan({{ $plan->id }})" role="button" tabindex="0"
     class="flex cursor-pointer flex-col rounded-xl border border-slate-200 nx-glass-tile p-5 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060]">
    <div class="mb-3 flex items-start justify-between">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="globe" class="h-3.5 w-3.5" />
            {{ $plan->type ?? 'Data' }}
        </span>
        <div class="flex items-center gap-2">
            @if ($plan->ai_tooltip)
                <div x-data="{ open: false }" class="relative" @click.stop>
                    <button type="button" @click="open = !open" @click.outside="open = false"
                            aria-label="What is this plan?"
                            class="flex h-6 w-6 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-primary dark:hover:bg-white/10">
                        <x-icon name="info" class="h-4 w-4" />
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 top-7 z-10 w-56 rounded-lg border border-slate-200 bg-white p-3 text-xs leading-relaxed text-slate-600 shadow-lg dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-slate-300">
                        {{ $plan->ai_tooltip }}
                    </div>
                </div>
            @endif
            @if ($plan->is_featured)
                <span class="inline-flex items-center gap-1 text-xs font-semibold text-accent">
                    <x-icon name="zap" class="h-3.5 w-3.5" /> Featured
                </span>
            @endif
        </div>
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
            <div class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $price['usd'] }}</div>
            @if ($price['local'])
                <div class="text-xs text-slate-500 dark:text-slate-400">≈ {{ $price['local'] }}</div>
            @endif
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-sm font-semibold text-white">
            View <x-icon name="chevron-right" class="h-4 w-4" />
        </span>
    </div>
</div>
