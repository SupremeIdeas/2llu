<div class="mx-auto max-w-lg">
    <a href="{{ route('catalogue') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to catalogue
    </a>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Checkout</h1>

        <div class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-[#243352]">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $plan->name }}</span>
                <span class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                    <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $plan->type ?? 'Data' }}
                </span>
            </div>
            <div class="mt-2 flex gap-4 text-sm text-slate-500 dark:text-slate-400">
                <span>{{ $plan->data_mb ? number_format($plan->data_mb / 1024, 1).' GB' : 'Unlimited' }}</span>
                <span>{{ $plan->validity_days ? $plan->validity_days.' days' : '—' }}</span>
            </div>
            <div class="mt-4 flex items-end justify-between border-t border-slate-200 pt-4 dark:border-[#2D4060]">
                <span class="text-sm text-slate-500 dark:text-slate-400">You pay</span>
                <div class="text-right">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $plan->display_price['usd'] }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $plan->display_price['ngn'] }}</div>
                </div>
            </div>
        </div>

        @if ($error)
            <div class="mt-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
            </div>
        @endif

        @if ($done)
            <div class="mt-4 flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                <x-icon name="badge-check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $message }}</span>
            </div>
            <a href="{{ route('dashboard') }}"
               class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white hover:bg-primary-dark">
                Go to My Connectivity <x-icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            <button type="button" wire:click="purchase" wire:loading.attr="disabled" wire:target="purchase"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="purchase" class="inline-flex items-center gap-2">
                    <x-icon name="shield-check" class="h-5 w-5" /> Pay with wallet
                </span>
                <span wire:loading wire:target="purchase" class="inline-flex items-center gap-2">
                    <x-icon name="refresh" class="h-5 w-5 animate-spin" /> Processing…
                </span>
            </button>
            <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">Charged securely from your NaaraSim wallet.</p>
        @endif
    </div>
</div>
