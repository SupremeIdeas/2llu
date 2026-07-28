@php
    // Deterministic brand tint when a logo/colour isn't available (graceful).
    $tint = fn ($p) => $p->brand_color ?: '#'.substr(md5($p->brand_key), 0, 6);
@endphp
<div class="mx-auto max-w-5xl" x-data="{ view: localStorage.getItem('nx_gift_view') || 'grid', detail: @entangle('selectedId') }"
     x-effect="localStorage.setItem('nx_gift_view', view)">
    {{-- Store entry preloader (self-hosted Lottie). A viewport-centred splash
         shown briefly while the storefront + brand logos settle, then faded —
         mirrors the global brand-preloader. Self-dismisses on a timer so it can
         never trap the page; reduced-motion shows the settled frame. --}}
    <div x-data="{ loading: true }" x-init="setTimeout(() => loading = false, 1300)"
         x-show="loading" x-transition:leave.opacity.duration.500ms
         class="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-3 bg-white/95 backdrop-blur-sm dark:bg-[#0D1B2A]/95"
         role="status" aria-live="polite">
        <x-lottie name="gift-preloader" label="Loading gift store" class="h-44 w-44" />
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Opening your gift store…</p>
    </div>

    {{-- Header — the Naara Gift storefront wears its own admin-set logo
         (falls back to the gift icon + wordmark when none is uploaded). --}}
    <div class="mb-5">
        <x-brand-logo variant="gift" label="Naara Gift" fallbackIcon="gift" class="h-9" />
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Gift cards for the brands you love — delivered instantly.</p>
    </div>

    {{-- Search + country + grid/list toggle --}}
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="relative min-w-[200px] flex-1">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search brands…"
                   class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
        </div>
        @if ($countries->isNotEmpty())
            <select wire:model.live="country" class="rounded-full border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                <option value="">All countries</option>
                @foreach ($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
        @endif
        <div class="flex gap-1 rounded-full bg-slate-100 p-1 dark:bg-white/5">
            <button type="button" @click="view = 'list'" :class="view === 'list' ? 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' : 'text-slate-400'" class="rounded-full p-1.5"><x-icon name="list" class="h-4 w-4" /></button>
            <button type="button" @click="view = 'grid'" :class="view === 'grid' ? 'bg-white text-primary shadow-sm dark:bg-white/15 dark:text-teal-300' : 'text-slate-400'" class="rounded-full p-1.5"><x-icon name="grid" class="h-4 w-4" /></button>
        </div>
    </div>

    {{-- Brand catalogue --}}
    @if ($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-sm text-slate-400 dark:border-white/10">No gift cards available yet.</div>
    @else
        <div :class="view === 'grid' ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4' : 'space-y-2'">
            @foreach ($products as $product)
                <button type="button" wire:click="select({{ $product->id }})" wire:key="gc-{{ $product->id }}"
                        class="group flex overflow-hidden rounded-2xl border border-slate-200 bg-white text-left transition hover:shadow-lg dark:border-white/10 dark:bg-slate-900/60"
                        :class="view === 'grid' ? 'flex-col' : 'flex-row items-center'">
                    <div class="flex shrink-0 items-center justify-center overflow-hidden"
                         :class="view === 'grid' ? 'aspect-[4/3] w-full' : 'h-16 w-16'"
                         style="background: linear-gradient(135deg, {{ $tint($product) }}22, {{ $tint($product) }}55);">
                        @if ($product->logo_url)
                            <img src="{{ $product->logo_url }}" alt="{{ $product->brand_name }}" loading="lazy" class="h-full w-full object-contain p-3">
                        @else
                            <span class="text-lg font-bold text-white" style="text-shadow: 0 1px 2px rgba(0,0,0,.3)">{{ \Illuminate\Support\Str::substr($product->brand_name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 p-3">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $product->brand_name }}</p>
                        <p class="text-xs text-slate-400">{{ $product->country }}@if ($product->category) · {{ $product->category }}@endif</p>
                    </div>
                </button>
            @endforeach
        </div>
        <div class="mt-5">{{ $products->links() }}</div>
    @endif

    {{-- Brand detail sheet --}}
    @if ($selected)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="$wire.close()" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="close"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
                {{-- Brand banner --}}
                <div class="relative flex h-36 items-center justify-center" style="background: linear-gradient(135deg, {{ $tint($selected) }}, #0D1B2A);">
                    <button type="button" wire:click="close" class="absolute right-3 top-3 rounded-full bg-black/30 p-1.5 text-white"><x-icon name="x" class="h-4 w-4" /></button>
                    @if ($selected->logo_url)
                        <img src="{{ $selected->logo_url }}" alt="" class="max-h-16 max-w-[60%] object-contain drop-shadow">
                    @else
                        <span class="text-2xl font-bold text-white">{{ $selected->brand_name }}</span>
                    @endif
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-5">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selected->brand_name }}</h2>
                    <p class="text-xs text-slate-400">{{ $selected->country }} · {{ $selected->currency }}</p>

                    {{-- Denomination selector --}}
                    <p class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Choose an amount</p>
                    @if (($denominations['type'] ?? '') === 'RANGE')
                        <input type="number" wire:model="amount" min="{{ $denominations['min'] }}" max="{{ $denominations['max'] }}"
                               placeholder="{{ $denominations['min'] }} – {{ $denominations['max'] }} {{ $selected->currency }}"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                        <p class="mt-1 text-xs text-slate-400">You pay retail; the exact charge is shown at checkout.</p>
                    @else
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (($denominations['options'] ?? []) as $opt)
                                <button type="button" wire:click="$set('amount', {{ $opt['face'] }})"
                                        @class(['rounded-xl border p-3 text-center transition', 'border-primary bg-primary/5 dark:bg-primary/10' => (float) $amount === $opt['face'], 'border-slate-200 hover:border-slate-300 dark:border-white/10' => (float) $amount !== $opt['face']])>
                                    <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $selected->currency }} {{ number_format($opt['face'], 0) }}</span>
                                    <span class="block text-[11px] text-slate-400">pay ${{ number_format($opt['retail'], 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Dynamic required fields --}}
                    @if (! empty($selected->required_fields))
                        <p class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recipient details</p>
                        <div class="space-y-2">
                            @foreach ($selected->required_fields as $field)
                                @php $k = $field['key'] ?? 'field'; @endphp
                                <input type="{{ $field['type'] ?? 'text' }}" wire:model="fields.{{ $k }}"
                                       placeholder="{{ $field['label'] ?? ucfirst($k) }}"
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                            @endforeach
                        </div>
                    @endif

                    {{-- Redemption note --}}
                    @if ($selected->redeem_instruction)
                        <details class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-white/5 dark:text-slate-400">
                            <summary class="cursor-pointer font-semibold">How to redeem</summary>
                            <p class="mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($selected->redeem_instruction), 400) }}</p>
                        </details>
                    @endif

                    {{-- Checkout — the money path --}}
                    <button type="button" wire:click="buy" wire:loading.attr="disabled" wire:target="buy" @disabled(! $amount)
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                        <span wire:loading.remove wire:target="buy"><x-icon name="gift" class="mr-1 inline h-4 w-4" /> Buy gift card</span>
                        <span wire:loading wire:target="buy" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Processing…</span>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-slate-400">Gift cards are final — no refunds once delivered.</p>
                </div>
            </div>
        </div>
    @endif
</div>
