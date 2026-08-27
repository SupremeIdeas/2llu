@php
    $hero = \App\Support\EsimHeroContent::current();
    $images = $hero['images'];
    $peek = count($images) >= 3; // a peek carousel needs 3+ to look right
@endphp
@if (! empty($images))
    @if ($peek)
        {{--
            Apple-style 3-up PEEK carousel (Theme Batch 1 §3). The centered slide
            sits at ~84% of the hero width with slivers of the previous/next slide
            visible at both edges, so depth reads instantly and the hero no longer
            "contains the screen" on desktop/tablet. Same container width as before;
            a taller band for a more premium feel. Corner radius + shadow follow the
            active theme's tokens (--radius-card). The data contract is unchanged —
            EsimHeroContent still returns the same images/title/description.
        --}}
        <div class="nx-imghero nx-imghero--peek relative mb-8 h-64 w-full sm:h-80 lg:h-[22rem]"
             x-data="esimHero({{ count($images) }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             tabindex="0" @keydown.arrow-left.prevent="prev()" @keydown.arrow-right.prevent="next()"
             role="region" aria-label="eSIM highlights" aria-roledescription="carousel">
            <div class="nx-imghero__track" :style="`transform: translateX(${8 - active * 84}%)`">
                @foreach ($images as $i => $src)
                    <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'"
                         @click="go({{ $i }})" role="button" tabindex="-1"
                         aria-label="Show highlight {{ $i + 1 }}">
                        <div class="relative h-full w-full border border-slate-200/60 bg-slate-100 shadow-sm dark:border-white/10 dark:bg-white/5">
                            <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async"
                                 width="1280" height="640" class="h-full w-full object-cover">
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Overlay: scrim + copy + dots, only over the CENTERED slide (peek
                 slides stay image-only — text on a cropped 55%-opacity peek is
                 illegible and cheapens the effect). --}}
            <div class="pointer-events-none absolute inset-y-0 left-1/2 w-[84%] -translate-x-1/2 overflow-hidden rounded-[var(--radius-card,1.5rem)]">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4 sm:p-6">
                    <div class="min-w-0">
                        <h1 class="line-clamp-2 text-lg font-bold leading-tight text-white sm:text-3xl">{{ $hero['title'] }}</h1>
                        <p class="mt-1 line-clamp-1 text-xs text-white/80 sm:text-base">{{ $hero['description'] }}</p>
                    </div>
                    <button type="button" @click="$dispatch('open-compatibility')"
                            class="pointer-events-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/95 px-4 py-2 text-xs font-semibold text-slate-900 shadow transition hover:bg-white sm:text-sm">
                        <x-icon name="signal" class="h-4 w-4 text-primary" /> Check compatibility
                    </button>
                </div>
                {{-- Dots — which slide is centered --}}
                <div class="pointer-events-auto absolute inset-x-0 bottom-2 flex justify-center gap-1.5 sm:hidden">
                    @foreach ($images as $i => $src)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            </div>

            {{-- Dots (desktop, top-right, clear of the copy) --}}
            <div class="absolute right-[9%] top-4 hidden gap-1.5 sm:flex">
                @foreach ($images as $i => $src)
                    <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                            class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                @endforeach
            </div>
        </div>
    @else
        {{--
            Fewer than 3 images: keep today's single-slide crossfade banner exactly
            as it shipped — a peek carousel with 1–2 slides would render broken
            peeks. Graceful-degrade to the proven behaviour (Batch 1 §3.3.5).
        --}}
        <div class="nx-imghero mb-8 aspect-[2/1] w-full overflow-hidden rounded-3xl border border-slate-200/60 shadow-sm dark:border-white/10"
             x-data="esimHero({{ count($images) }})"
             @mouseenter="pause()" @mouseleave="resume()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
             role="region" aria-label="eSIM highlights">
            @foreach ($images as $i => $src)
                <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                    <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="640">
                </div>
            @endforeach

            {{-- Bottom scrim for legibility --}}
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

            {{-- Copy + compatibility pill --}}
            <div class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 p-4 sm:p-5">
                <div class="min-w-0">
                    <h1 class="line-clamp-2 text-base font-bold leading-tight text-white sm:text-2xl">{{ $hero['title'] }}</h1>
                    <p class="mt-1 line-clamp-1 text-xs text-white/80 sm:text-sm">{{ $hero['description'] }}</p>
                </div>
                <button type="button" @click="$dispatch('open-compatibility')"
                        class="pointer-events-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/95 px-4 py-2 text-xs font-semibold text-slate-900 shadow transition hover:bg-white sm:text-sm">
                    <x-icon name="signal" class="h-4 w-4 text-primary" /> Check compatibility
                </button>
            </div>

            {{-- Pagination dots (top-right, clear of the copy) --}}
            @if (count($images) > 1)
                <div class="absolute right-4 top-4 flex gap-1.5">
                    @foreach ($images as $i => $src)
                        <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                                class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @once
        <script>
            function esimHero(count) {
                return {
                    active: 0, timer: null, sx: 0,
                    init() { this.start(); },
                    start() { if (count > 1) this.timer = setInterval(() => this.next(), 5000); },
                    pause() { clearInterval(this.timer); },
                    resume() { this.pause(); this.start(); },
                    next() { this.active = (this.active + 1) % count; },
                    prev() { this.active = (this.active - 1 + count) % count; this.resume(); },
                    go(i) { this.active = i; this.resume(); },
                    touchStart(e) { this.sx = e.changedTouches[0].screenX; },
                    touchEnd(e) {
                        const dx = e.changedTouches[0].screenX - this.sx;
                        if (Math.abs(dx) < 40) return;
                        this.active = dx < 0 ? (this.active + 1) % count : (this.active - 1 + count) % count;
                        this.resume();
                    },
                };
            }
        </script>
    @endonce
@endif
