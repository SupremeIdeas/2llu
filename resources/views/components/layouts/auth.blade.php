@props(['title' => null, 'heading' => null, 'subheading' => null])

{{-- Two-column auth shell (Module 28). Desktop: an admin-set media panel
     (WebP/JPEG image or a short muted video) on the left, the form on the
     right; the panel collapses on mobile to a compact branded header. Every
     auth page ends with the assignable footer (Supreme Ideas Agency + legal
     links). Dark mode + reduced-motion throughout. --}}
@php($panel = \App\Support\SiteChrome::authPanel())
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name()">
    <div class="flex min-h-screen flex-col">
        <div class="flex flex-1 flex-col lg:flex-row">
            {{-- Media panel --}}
            <div class="relative overflow-hidden bg-gradient-to-br from-primary via-primary-dark to-navy lg:w-1/2">
                @if (\App\Support\SiteChrome::hasAuthMedia() && $panel['media_type'] === 'video')
                    <video class="absolute inset-0 h-full w-full object-cover opacity-70" autoplay muted loop playsinline
                           @if ($panel['poster_url']) poster="{{ $panel['poster_url'] }}" @endif
                           aria-hidden="true">
                        <source src="{{ $panel['media_url'] }}">
                    </video>
                @elseif (\App\Support\SiteChrome::hasAuthMedia())
                    <img src="{{ $panel['media_url'] }}" alt="" aria-hidden="true"
                         class="absolute inset-0 h-full w-full object-cover opacity-70">
                @else
                    {{-- Branded default: floating glow orbs. --}}
                    <span class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-accent/25 blur-3xl"></span>
                    <span class="pointer-events-none absolute -bottom-24 -left-10 h-72 w-72 rounded-full bg-white/10 blur-3xl"></span>
                @endif
                <div class="absolute inset-0 bg-navy/45"></div>

                <div class="relative flex h-full flex-col justify-between p-8 lg:p-12">
                    <a href="{{ route('home') }}" class="inline-flex">
                        <x-brand-logo variant="product" class="h-9 max-w-[180px] brightness-0 invert" fallback-icon="signal" />
                    </a>
                    <div class="hidden lg:block">
                        <h2 class="font-display text-3xl font-bold leading-tight text-white xl:text-4xl">{{ $panel['headline'] }}</h2>
                        <p class="mt-4 max-w-md text-sm leading-relaxed text-teal-100/90">{{ $panel['subtext'] }}</p>
                    </div>
                    <p class="hidden text-xs font-medium uppercase tracking-widest text-teal-100/70 lg:block">Supreme Ideas Agency</p>
                </div>
            </div>

            {{-- Form column --}}
            <div class="flex flex-1 items-center justify-center bg-[#F8F9FA] px-4 py-10 dark:bg-navy lg:w-1/2">
                <div class="w-full max-w-sm">
                    <div class="mb-6 flex items-center justify-between lg:hidden">
                        <a href="{{ route('home') }}"><x-brand-logo variant="product" class="h-8 max-w-[150px]" fallback-icon="signal" /></a>
                        <x-theme-toggle />
                    </div>

                    <div class="mb-6">
                        <div class="mb-4 hidden justify-end lg:flex"><x-theme-toggle /></div>
                        @if ($heading)
                            <h1 class="font-display text-2xl font-bold text-slate-900 dark:text-white">{{ $heading }}</h1>
                        @endif
                        @if ($subheading)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subheading }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-site-footer variant="slim" />
    </div>
</x-layouts.app>
