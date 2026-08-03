{{-- A country / region / global navigation tile (BUILD-8 §3.2): admin image if
     set, else a clean fallback (flag for a country, glyph for a region), the
     name, a live "from $X.XX" teaser, and a plan count.
     Expects: $click (wire action), $title, $img (?url), $flag (?ISO2),
              $fromUsd (?float), $count (int), $fmt. --}}
@php($teaser = $fromUsd !== null ? $fmt((float) $fromUsd) : null)
<button type="button" wire:click="{{ $click }}"
        class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 nx-glass-tile text-left shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060]">
    <div class="relative flex h-28 w-full items-center justify-center overflow-hidden bg-slate-100 dark:bg-[#152238]">
        @if ($img)
            <img src="{{ $img }}" alt="{{ $title }}" loading="lazy"
                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
        @elseif ($flag)
            <x-country-flag :country="$flag" class="h-12 w-16 rounded shadow-sm" />
        @else
            <x-icon name="globe" class="h-10 w-10 text-primary/60" gradient />
        @endif
        <span class="absolute right-2 top-2 rounded-full bg-black/45 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
            {{ $count }} {{ \Illuminate\Support\Str::plural('plan', $count) }}
        </span>
    </div>
    <div class="flex flex-1 items-end justify-between gap-2 p-3">
        <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</div>
            @if ($teaser)
                <div class="text-xs text-slate-500 dark:text-slate-400">from {{ $teaser['usd'] }}</div>
            @endif
        </div>
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-400 group-hover:text-primary" />
    </div>
</button>
