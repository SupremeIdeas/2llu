@php
    $hero = \App\Support\NumbersHeroContent::current();
    $images = $hero['images'];
@endphp
@if (! empty($images))
    {{--
        Compact Play-Store-style banner (Numbers V6 §0): a clean full-bleed
        image with a strong bottom scrim, the admin title/subtitle bottom-left
        and an Explore pill — sized like the app's promo banners, not a giant
        block. 4-image reveal with auto-advance, dots and mobile swipe.
    --}}
    <div class="nx-imghero mb-6 aspect-[2/1] w-full overflow-hidden rounded-3xl border border-slate-200/70 shadow-sm dark:border-white/10"
         x-data="numbersHero({{ count($images) }})"
         @mouseenter="pause()" @mouseleave="resume()"
         @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
         role="region" aria-label="Numbers highlights">
        @foreach ($images as $i => $src)
            <div class="nx-imghero__slide" :class="active === {{ $i }} && 'is-active'">
                <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="480">
            </div>
        @endforeach

        {{-- Bottom scrim for legibility --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

        {{-- Copy (bottom-left) --}}
        <div class="absolute inset-x-0 bottom-0 p-4 sm:p-6">
            <div class="min-w-0 max-w-lg">
                <h1 class="line-clamp-2 text-lg font-bold leading-tight text-white sm:text-2xl">{{ $hero['title'] }}</h1>
                <p class="mt-1 line-clamp-1 text-xs text-white/80 sm:text-sm">{{ $hero['description'] }}</p>
            </div>
        </div>

        {{-- Pagination dots (top-right, out of the copy's way) --}}
        @if (count($images) > 1)
            <div class="absolute right-4 top-4 flex gap-1.5">
                @foreach ($images as $i => $src)
                    <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                            class="h-1.5 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5'"></button>
                @endforeach
            </div>
        @endif
    </div>

    @once
        <script>
            function numbersHero(count) {
                return {
                    active: 0, timer: null, sx: 0,
                    init() { this.start(); },
                    start() { if (count > 1) this.timer = setInterval(() => this.next(), 5000); },
                    pause() { clearInterval(this.timer); },
                    resume() { this.pause(); this.start(); },
                    next() { this.active = (this.active + 1) % count; },
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
