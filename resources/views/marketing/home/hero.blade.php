{{-- Hero (CMS: home.hero). Optional admin image becomes the backdrop. --}}
<section class="relative overflow-hidden" data-hero-sentinel>
    @if (! empty($s['image']))
        {{-- Apple-style hero media: admin-uploaded image with a slow GSAP
             parallax scale as the visitor scrolls (Module 27.5). --}}
        <div class="absolute inset-0 overflow-hidden">
            <img src="{{ $s['image'] }}" alt="" data-hero-media class="h-full w-full object-cover will-change-transform">
            <div class="absolute inset-0 bg-gradient-to-b from-navy/60 via-navy/70 to-navy/85"></div>
        </div>
    @endif
    <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-16 text-center sm:pt-24 {{ ! empty($s['image']) ? 'text-white' : '' }}">
        <p data-reveal class="text-xs font-semibold uppercase tracking-[0.25em] text-accent">{{ $s['eyebrow'] }}</p>
        <h1 data-reveal style="--reveal-delay:.08s"
            class="mx-auto mt-4 max-w-3xl text-4xl font-bold leading-tight sm:text-6xl {{ empty($s['image']) ? 'text-slate-900 dark:text-white' : '' }}">
            {{ $s['headline'] }}
        </h1>
        <p data-reveal style="--reveal-delay:.16s"
           class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed {{ empty($s['image']) ? 'text-slate-600 dark:text-slate-300' : 'text-slate-200' }}">
            {{ $s['subheadline'] }}
        </p>
        <div data-reveal style="--reveal-delay:.24s" class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--primary !px-8 !py-3 !text-base">{{ $s['cta_primary'] }}</a>
            <a href="{{ route('how-it-works') }}" class="nx-btn nx-btn--ghost !px-8 !py-3 !text-base">{{ $s['cta_secondary'] }}</a>
        </div>
        <p data-reveal style="--reveal-delay:.32s" class="mt-6 text-xs {{ empty($s['image']) ? 'text-slate-400' : 'text-slate-300' }}">{{ $s['social_proof'] }}</p>

        <dl data-reveal style="--reveal-delay:.4s" class="mx-auto mt-12 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach (['stat_1', 'stat_2', 'stat_3', 'stat_4'] as $stat)
                @php [$value, $label] = array_pad(explode(' ', $s[$stat], 2), 2, ''); @endphp
                @php($numeric = preg_match('/^(\d+)(\+?)$/', $value, $m))
                <div class="nx-card !p-4 text-center">
                    <dt class="sr-only">{{ $label }}</dt>
                    <dd class="font-display text-2xl font-bold text-primary dark:text-teal-300"
                        @if ($numeric) data-countup="{{ $m[1] }}" data-suffix="{{ $m[2] }}" @endif>{{ $value }}</dd>
                    <dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
