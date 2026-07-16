{{-- How it works (CMS: home.how) — STACKING step cards on scroll. --}}
<section class="mx-auto max-w-4xl px-4 py-20" data-bg="light">
    <div class="text-center">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
        <h2 data-reveal class="mt-3 text-3xl font-bold text-slate-900 sm:text-4xl dark:text-white">{{ $s['headline'] }}</h2>
        <p data-reveal class="mx-auto mt-3 max-w-xl text-slate-600 dark:text-slate-300">{{ $s['subtext'] }}</p>
    </div>

    <div class="mkt-stack mt-12">
        @foreach ([1, 2, 3] as $n)
            <div class="mkt-stack__card border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#16233d]" style="top: calc(5.5rem + {{ ($n - 1) * 1.25 }}rem)">
                <div class="flex items-start gap-5">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary font-display text-xl font-bold text-white">{{ $n }}</span>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">{{ $s["step_{$n}_title"] }}</h3>
                        <p class="mt-2 leading-relaxed text-slate-600 dark:text-slate-300">{{ $s["step_{$n}_text"] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div data-reveal class="mt-12 text-center">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary !px-8 !py-3">{{ $s['cta'] }}</a>
    </div>
</section>
