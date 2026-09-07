{{-- Per-theme custom About page — "midnight-signal" (Theme visual rebuild,
     owner request 2026-09-07). Reuses the theme's own dark navy / cyan "HUD
     radar ring" motif (same as its login screen and landing hero) so the
     persona stays consistent across every page. Editable text comes from
     $content (ThemePageLibrary schema for about_page/midnight-signal); the
     values grid is structural, matching the landing page's own feature-grid
     precedent. Fully responsive: two-column bands collapse below lg. --}}
<section class="relative overflow-hidden bg-navy">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <span class="absolute left-1/2 top-0 h-[30rem] w-[30rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-primary/25 blur-3xl"></span>
    </div>

    <div class="relative mx-auto max-w-3xl px-4 pb-14 pt-16 text-center sm:pb-20 sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary">
            <x-icon name="signal" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-tight text-white sm:text-5xl">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
            {{ $content['intro'] }}
        </p>

        {{-- Radar-ring motif, echoing the landing hero's central visual. --}}
        <div class="relative mx-auto mt-12 flex h-40 w-40 items-center justify-center" aria-hidden="true">
            <span class="absolute h-20 w-20 rounded-full border border-primary/25"></span>
            <span class="absolute h-32 w-32 rounded-full border border-primary/15"></span>
            <span class="absolute h-40 w-40 animate-ping rounded-full border border-primary/10" style="animation-duration:3s"></span>
            <span class="relative flex h-3 w-3 rounded-full bg-primary shadow-lg shadow-primary/70"></span>
        </div>
    </div>
</section>

{{-- Mission / vision — dark data-readout cards, same border/backdrop language as the landing hero's floating stats. --}}
<section class="bg-navy px-4 pb-16">
    <div class="mx-auto grid max-w-5xl gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-primary/20 bg-navy/90 p-6 shadow-xl backdrop-blur">
            <p class="text-[10px] uppercase tracking-wide text-primary">Mission</p>
            <p class="mt-2 text-sm leading-relaxed text-slate-200">{{ $content['mission'] }}</p>
        </div>
        <div class="rounded-2xl border border-primary/20 bg-navy/90 p-6 shadow-xl backdrop-blur">
            <p class="text-[10px] uppercase tracking-wide text-primary">Vision</p>
            <p class="mt-2 text-sm leading-relaxed text-slate-200">{{ $content['vision'] }}</p>
        </div>
    </div>
</section>

{{-- Values grid — structural echo of the landing page's light feature grid. --}}
<section class="bg-[#F8F9FA] py-16 dark:bg-[#0c1220] sm:py-20">
    <div class="mx-auto max-w-5xl px-4">
        <h2 class="text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">How we hold the signal</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'signal', 'title' => 'Always watching', 'body' => 'Carrier quality and coverage are checked continuously, not once a quarter.'],
                ['icon' => 'shield-check', 'title' => 'No surprise bills', 'body' => 'What you see at checkout is the final price — cost is never marked up after the fact.'],
                ['icon' => 'clock', 'title' => 'Answers in minutes', 'body' => 'When a network hiccups, the signal room notices before you do.'],
            ] as $feature)
                <div class="rounded-2xl border border-primary/10 bg-white p-5 dark:border-primary/15 dark:bg-navy/60">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                        <x-icon :name="$feature['icon']" class="h-4 w-4" />
                    </span>
                    <h3 class="mt-3 text-sm font-bold text-slate-900 dark:text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Founder card. --}}
<section class="mx-auto max-w-2xl px-4 py-20">
    <div class="rounded-2xl border border-primary/15 bg-white p-8 text-center dark:border-primary/20 dark:bg-navy/60">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-primary/30 bg-primary/10 text-xl font-bold text-primary">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <h3 class="mt-4 font-display text-xl font-bold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</h3>
        <p class="text-sm font-medium text-primary">{{ $content['founder_title'] }}</p>
        <p class="mx-auto mt-4 max-w-lg text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
    </div>
</section>

@include('marketing._reused-sections')
