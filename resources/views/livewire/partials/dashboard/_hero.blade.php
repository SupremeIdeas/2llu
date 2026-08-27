@php($heroLight = \App\Support\HeroBackground::light())
@php($heroDark = \App\Support\HeroBackground::dark())
@php($hasHero = \App\Support\HeroBackground::showsOnDashboard())
@php($heroDesc = \App\Support\HeroBackground::description())
{{-- Per-theme home hero (owner request): each theme carries its own hero image.
     An admin-uploaded HeroBackground still wins; otherwise the active theme's
     hero shows. --}}
@php($themeHero = \App\Support\ThemePreset::heroFor('dashboard'))
@php($heroImg = ($hasHero ? ($heroLight ?: $heroDark) : null) ?: $themeHero)
@php($heroImgDark = ($hasHero && $heroDark) ? $heroDark : $themeHero)

{{--
    Dashboard home hero (reference-matched). Text sits LEFT; the photo bleeds into
    the top-right corner with NO card and NO border, dissolving into the page
    background on its left + bottom edges via a mask (faint over the light bg, a
    deeper fade over dark). Two pill CTAs below. When no image is set it degrades
    cleanly to the headline + pills with no empty gap.
--}}
<section class="nx-home-hero mb-8">
    @if ($heroImg)
        <div class="nx-home-hero__media" aria-hidden="true">
            <img src="{{ $heroImg }}" alt="" loading="eager" decoding="async"
                 class="nx-home-hero__img {{ ($heroImgDark && $heroImgDark !== $heroImg) ? 'dark:hidden' : '' }}">
            @if ($heroImgDark && $heroImgDark !== $heroImg)
                <img src="{{ $heroImgDark }}" alt="" loading="eager" decoding="async"
                     class="nx-home-hero__img hidden dark:block">
            @endif
        </div>
    @endif

    <div class="relative z-10 max-w-[62%] pt-1 sm:max-w-[58%]">
        <h1 class="font-display text-[2.35rem] font-extrabold leading-[1.03] tracking-tight text-slate-900 dark:text-white sm:text-5xl">
            My<br>
            <span class="bg-gradient-to-r from-primary to-teal-500 bg-clip-text text-transparent dark:from-teal-300 dark:to-teal-400">Connectivity</span>
        </h1>
        <p class="mt-3 max-w-xs text-sm leading-relaxed text-slate-500 dark:text-slate-300 sm:text-base">{{ $heroDesc }}</p>

        <div class="mt-6 flex items-center gap-2.5">
            <a href="{{ route('catalogue') }}" wire:navigate
               class="group inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-primary via-primary-dark to-navy px-5 py-3.5 text-[15px] font-bold text-white shadow-lg shadow-primary/25 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/35 sm:text-base">
                <x-icon name="id-card" class="h-5 w-5" /> Buy eSIM
                <x-icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
            </a>
            <a href="{{ route('numbers') }}" wire:navigate
               class="group inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3.5 text-[15px] font-bold text-slate-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-[#16233d] dark:text-white sm:text-base">
                <x-icon name="hash" class="h-5 w-5 text-primary dark:text-teal-300" /> Get Number
                <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5" />
            </a>
        </div>
    </div>
</section>
