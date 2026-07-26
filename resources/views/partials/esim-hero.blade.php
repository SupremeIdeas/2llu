@php
    $hero = \App\Support\EsimHeroContent::current();
    $images = $hero['images'];
@endphp
@if (! empty($images))
    <div class="nx-hero mb-8 aspect-[16/9] rounded-3xl border border-slate-200/60 sm:aspect-[21/9] dark:border-white/10"
         x-data="esimHero({{ count($images) }})"
         @mouseenter="pause()" @mouseleave="resume()"
         @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
         role="region" aria-label="eSIM highlights">
        {{-- Reveal slides --}}
        @foreach ($images as $i => $src)
            <div class="nx-hero__slide" :class="active === {{ $i }} && 'is-active'" @if($i === 0) x-init="" @endif>
                <img src="{{ $src }}" alt="" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" decoding="async" width="1280" height="540">
            </div>
        @endforeach

        {{-- Gradient scrim + copy --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8">
            <h1 class="max-w-lg text-xl font-bold leading-tight text-white sm:text-3xl">{{ $hero['title'] }}</h1>
            <p class="mt-1.5 max-w-md text-sm text-white/85 sm:text-base">{{ $hero['description'] }}</p>
            <button type="button" @click="$dispatch('open-compatibility')"
                    class="pointer-events-auto mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-4 py-2 text-sm font-semibold text-slate-900 shadow hover:bg-white">
                <x-icon name="signal" class="h-4 w-4 text-primary" /> Check compatibility
            </button>
        </div>

        {{-- Pagination dots --}}
        @if (count($images) > 1)
            <div class="absolute right-4 top-4 flex gap-1.5">
                @foreach ($images as $i => $src)
                    <button type="button" @click="go({{ $i }})" aria-label="Show highlight {{ $i + 1 }}"
                            class="h-2 rounded-full bg-white/50 transition-all" :class="active === {{ $i }} ? 'w-5 bg-white' : 'w-2'"></button>
                @endforeach
            </div>
        @endif
    </div>

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
