{{-- Per-theme custom About page — "neon-vertex" (Theme visual rebuild, owner
     request 2026-09-07: "for each theme, will and must carry its own...
     about us page"). Same visual language as this theme's landing hero —
     ultraviolet/hot-pink gradient blobs, pill badges, font-display gradient
     headline, rounded-[2.5rem] card curves — so the About page reads as one
     continuous persona rather than a bolted-on default page. Editable text
     comes from $content (ThemePreset::pageContent('about_page') against
     ThemePageLibrary's schema); the values grid and mission/vision band are
     structural, matching the landing page's own feature-card precedent.
     Fully responsive: two-column bands collapse to one column below lg. --}}
<section class="relative overflow-hidden bg-white dark:bg-navy">
    <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-gradient-to-br from-primary/30 via-accent/25 to-transparent blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-20 top-1/3 h-72 w-72 rounded-full bg-accent/20 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-20 text-center sm:pt-24">
        <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary dark:border-primary/30 dark:bg-primary/10 dark:text-teal-300">
            <x-icon name="zap" class="h-3.5 w-3.5" /> {{ $content['eyebrow'] }}
        </span>
        <h1 class="mx-auto mt-5 max-w-xl font-display text-4xl font-bold leading-[1.1] text-slate-900 sm:text-5xl dark:text-white">
            {{ $content['headline'] }}
        </h1>
        <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
            {{ $content['intro'] }}
        </p>
    </div>
</section>

{{-- Mission / vision band — curved two-panel layout, gradient split. --}}
<section class="relative mx-auto max-w-5xl px-4 pb-16">
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 dark:border-white/10 dark:bg-[#12172a]">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                <x-icon name="target" class="h-5 w-5" />
            </span>
            <h2 class="mt-5 font-display text-xl font-bold text-slate-900 dark:text-white">Our mission</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['mission'] }}</p>
        </div>
        <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 dark:border-white/10 dark:bg-[#12172a]">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-accent to-primary text-white">
                <x-icon name="eye" class="h-5 w-5" />
            </span>
            <h2 class="mt-5 font-display text-xl font-bold text-slate-900 dark:text-white">Our vision</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['vision'] }}</p>
        </div>
    </div>
</section>

{{-- Values grid — structural echo of the landing page's 3 feature cards. --}}
<section class="mx-auto max-w-5xl px-4 pb-20">
    <h2 class="text-center font-display text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">What we build on</h2>
    <div class="mt-10 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['icon' => 'shield-check', 'title' => 'Radical transparency', 'body' => 'The price you see is the price you pay — no hidden roaming fees, ever.'],
            ['icon' => 'globe', 'title' => 'Built for the continent', 'body' => 'Designed first for African travellers, then extended to 190+ countries.'],
            ['icon' => 'sparkles', 'title' => 'Obsessed with speed', 'body' => 'From download to a working eSIM in under two minutes, every time.'],
        ] as $card)
            <div class="rounded-3xl border border-slate-200 bg-white p-6 transition hover:-translate-y-1 hover:shadow-lg dark:border-white/10 dark:bg-[#12172a]">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-white">
                    <x-icon :name="$card['icon']" class="h-5 w-5" />
                </span>
                <h3 class="mt-4 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $card['title'] }}</h3>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $card['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Founder card — gradient-ring avatar frame matching the landing page's stat-card curve language. --}}
<section class="mx-auto max-w-2xl px-4 pb-24">
    <div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 text-center dark:border-white/10 dark:bg-[#12172a]">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-primary to-accent text-xl font-bold text-white">
            {{ collect(explode(' ', $content['founder_name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
        </span>
        <h3 class="mt-4 font-display text-xl font-bold text-slate-900 dark:text-white">{{ $content['founder_name'] }}</h3>
        <p class="text-sm font-medium text-primary dark:text-teal-300">{{ $content['founder_title'] }}</p>
        <p class="mx-auto mt-4 max-w-lg text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $content['founder_bio'] }}</p>
    </div>
</section>

@include('marketing._reused-sections')
