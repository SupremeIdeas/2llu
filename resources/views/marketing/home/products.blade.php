{{-- Products showcase (CMS: home.products) — DYNAMIC CONTENT SWITCH ON SCROLL:
     GSAP pins this section and crossfades the three value panels as the
     visitor scrolls (Module 27.5). Without JS/GSAP it renders as a clean
     stacked list, so nothing is ever hidden. --}}
@php
    // The Naara product family. Only render a panel when its title exists, so the
    // gift card (p4) appears once its copy is set. Icons map 1:1 to the panels.
    $icons = ['globe', 'hash', 'phone', 'gift'];
    $panels = collect([1, 2, 3, 4])->filter(fn ($n) => ! empty($s["p{$n}_title"] ?? null))->values();
    $href = function ($n) {
        if (! auth()->check()) {
            return route('register');
        }

        return match ((int) $n) {
            1 => route('catalogue'),
            4 => \App\Support\FeatureFlags::enabled('naara_gift') ? route('gift-cards') : route('dashboard'),
            default => route('numbers'),
        };
    };
@endphp
<section data-products-pin class="flex min-h-screen flex-col justify-center px-4 py-20" data-bg="light">
    <div class="mx-auto w-full max-w-4xl">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
            <h2 class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
            @if (! empty($s['subtext']))
                <p class="mx-auto mt-4 max-w-2xl leading-relaxed text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
            @endif
        </div>

        {{-- Panels flow as a normal stacked list; the .gsap-pin class (added
             only when GSAP initialises) overlaps them for the crossfade. --}}
        <div class="relative mt-12 grid min-h-[19rem] gap-6">
            @foreach ($panels as $n)
                <div data-product-panel>
                    <div class="nx-card mx-auto max-w-2xl !p-8 text-center sm:!p-10">
                        @if (! empty($s['image']) && $n === 1)
                            <img src="{{ $s['image'] }}" alt="" class="mx-auto mb-6 h-36 w-full max-w-sm rounded-2xl object-cover">
                        @else
                            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                                <x-icon :name="$icons[$n - 1]" class="h-7 w-7" />
                            </span>
                        @endif
                        <h3 class="mt-5 font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $s["p{$n}_title"] }}</h3>
                        <p class="mx-auto mt-3 max-w-lg leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["p{$n}_text"] }}</p>
                        <a href="{{ $href($n) }}" class="nx-btn nx-btn--primary mt-6 !px-7">{{ $s["p{$n}_cta"] }}</a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Progress dots (visual only; GSAP crossfades the panels). --}}
        <div class="mt-8 flex justify-center gap-2" aria-hidden="true">
            @foreach ($panels as $n)
                <span class="h-1.5 w-6 rounded-full bg-primary/25"></span>
            @endforeach
        </div>
    </div>
</section>
