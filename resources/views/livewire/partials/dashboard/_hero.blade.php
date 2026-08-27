{{-- Dashboard home hero block (Theme Batch 2 §2) — extracted verbatim so every
     structural variant shares the exact same data + markup, only re-ordered. --}}
@php($heroLight = \App\Support\HeroBackground::light())
@php($heroDark = \App\Support\HeroBackground::dark())
@php($hasHero = \App\Support\HeroBackground::showsOnDashboard())
@php($heroDesc = \App\Support\HeroBackground::description())

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Connectivity</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $heroDesc }}</p>

    @if ($hasHero)
        <div class="mt-3 aspect-[2/1] max-h-52 w-full overflow-hidden rounded-2xl border border-slate-200/70 dark:border-white/10 sm:max-h-64">
            <img src="{{ $heroLight ?: $heroDark }}" alt="" loading="lazy" decoding="async"
                 class="h-full w-full object-cover object-center {{ $heroDark ? 'dark:hidden' : '' }}">
            @if ($heroDark)
                <img src="{{ $heroDark }}" alt="" loading="lazy" decoding="async"
                     class="hidden h-full w-full object-cover object-center dark:block">
            @endif
        </div>
    @endif

    {{-- Bento action tiles — Buy eSIM / Get number side by side at every width. --}}
    <div class="mt-4 grid grid-cols-2 gap-3">
        <x-bento-tile bkey="buy-esim" label="Buy eSIM" variant="primary"
                      :href="route('catalogue')" wire:navigate />
        <x-bento-tile bkey="get-number" label="Get number"
                      :href="route('numbers')" wire:navigate />
    </div>
</div>
