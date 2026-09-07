{{-- Per-theme custom How It Works page — "neon-vertex" (Theme visual
     rebuild, owner request 2026-09-07). Same gradient-blob/pill/curved-card
     language as this theme's landing hero and About page. Editable text
     ($content) covers headline/subtext; the 4-step flow and device
     compatibility callout are structural, matching the landing page's own
     feature-card precedent. Fully responsive: numbered rail collapses to a
     single column below sm. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-gradient-to-br from-primary/30 via-accent/25 to-transparent blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-20 text-center sm:pt-24">
        <h1 class="mx-auto max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['subtext'] }}
        </p>
    </div>
</section>

{{-- 4-step numbered flow — gradient badge numerals, rounded card rail. --}}
<section class="mx-auto max-w-3xl px-4 pb-16">
    <ol class="relative space-y-6 pl-10">
        <span class="absolute bottom-1 left-4 top-1 w-0.5 rounded-full bg-gradient-to-b from-primary to-accent" aria-hidden="true"></span>
        @foreach ([
            ['title' => 'Pick your destination', 'body' => 'Search any of 190+ countries and see live plans in seconds — no account required to browse.'],
            ['title' => 'Choose a plan, pay once', 'body' => 'Your wallet covers it — top up with card, bank transfer, or mobile money.'],
            ['title' => 'Scan the QR', 'body' => 'Your eSIM QR lands instantly by email and in-app. Scan it before you fly, or the moment you land.'],
            ['title' => 'Land already connected', 'body' => 'No SIM counters, no roaming toggles to remember — you touch down online.'],
        ] as $i => $step)
            <li class="relative">
                <span class="absolute -left-10 top-0 flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-sm font-bold text-white shadow-lg shadow-accent/30">{{ $i + 1 }}</span>
                <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-[#12172a]">
                    <h3 class="font-display font-bold text-slate-900 dark:text-white">{{ $step['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $step['body'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</section>

{{-- Device compatibility callout — gradient card, matches the landing hero's stat-card curve. --}}
<section class="mx-auto max-w-3xl px-4 pb-24">
    <div class="rounded-[2.5rem] bg-gradient-to-br from-primary to-accent p-8 text-center text-white shadow-2xl shadow-accent/30">
        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-white/15">
            <x-icon name="smartphone" class="h-5 w-5" />
        </span>
        <h2 class="mt-4 font-display text-xl font-bold">Check your phone supports eSIM first</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-white/90">Most phones from the last 4 years do — we check automatically before you pay, so there's never a wasted purchase.</p>
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" wire:navigate class="mt-6 inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-semibold text-primary shadow-lg transition hover:opacity-90">
            Check compatibility <x-icon name="chevron-right" class="h-4 w-4" />
        </a>
    </div>
</section>

@include('marketing._reused-sections')
