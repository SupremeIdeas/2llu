{{-- Per-theme custom How It Works page — "midnight-signal" (Theme visual
     rebuild, owner request 2026-09-07). Reuses the dark navy / cyan
     data-readout card language from this theme's landing hero and About
     page. Editable text ($content) covers headline/subtext; the 4-step
     flow and compatibility callout are structural, matching the landing
     page's own feature-grid precedent. Fully responsive: numbered rail
     collapses to a single column below sm. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[26rem] w-[26rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/20 blur-3xl"></span>
    </div>
    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:pb-16 sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

{{-- 4-step signal path — dark readout cards with a cyan progress rail. --}}
<section class="bg-navy px-4 pb-20">
    <ol class="relative mx-auto max-w-2xl space-y-5 pl-10">
        <span class="absolute bottom-1 left-4 top-1 w-0.5 rounded-full bg-primary/20" aria-hidden="true"></span>
        @foreach ([
            ['title' => 'We scan the route', 'body' => 'Every carrier along your destination is checked for live coverage and quality before you ever see a plan.'],
            ['title' => 'You lock in a rate', 'body' => 'Your wallet pays once, at the price shown — no roaming surcharges added later.'],
            ['title' => 'The signal room activates you', 'body' => 'Your eSIM QR is issued and monitored from the moment it\'s generated.'],
            ['title' => 'You land already tracked', 'body' => 'If a network dips mid-trip, the signal room flags it before you notice a dropped bar.'],
        ] as $i => $step)
            <li class="relative">
                <span class="absolute -left-10 top-0 flex h-8 w-8 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-sm font-bold text-primary">{{ $i + 1 }}</span>
                <div class="rounded-2xl border border-primary/20 bg-navy/90 p-5 shadow-xl backdrop-blur">
                    <h3 class="font-display font-bold text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-300">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>

{{-- Device compatibility callout. --}}
<section class="bg-[#F8F9FA] px-4 py-20 dark:bg-[#0c1220]">
    <div class="mx-auto max-w-2xl rounded-2xl border border-primary/15 bg-white p-8 text-center dark:border-primary/20 dark:bg-navy/60">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
            <x-icon name="smartphone" class="h-5 w-5" />
        </span>
        <h2 class="mt-4 font-display text-xl font-bold text-slate-900 dark:text-white">Check your phone supports eSIM first</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted purchase.</p>
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/40 transition hover:bg-primary-dark">
            Check compatibility <x-icon name="chevron-right" class="h-4 w-4" />
        </a>
    </div>
</section>

@include('marketing._reused-sections')
