{{-- Public marketing shell (Module 27): sticky glassy nav, CMS-driven pages,
     footer with Supreme Ideas Agency attribution + legal quick-links. Guests
     get Sign in / Get Started; signed-in visitors go straight to their
     dashboard. --}}
@props(['title' => null])
<x-layouts.app :title="$title ?? \App\Support\BrandSettings::name().' — Stay Connected. No Borders. No Swaps.'">
    <div class="mkt-bg min-h-screen">
        {{-- Nav --}}
        <header class="sticky top-0 z-50 border-b border-slate-200/60 bg-white/80 backdrop-blur-xl dark:border-white/10 dark:bg-[#0D1B2A]/85">
            <nav class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('home') }}" class="shrink-0"><x-brand-logo variant="product" class="h-8 max-w-[150px]" /></a>

                <div class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex dark:text-slate-300">
                    <a href="{{ route('how-it-works') }}" class="transition hover:text-primary">How It Works</a>
                    <a href="{{ route('pricing') }}" class="transition hover:text-primary">Pricing</a>
                    <a href="{{ route('about') }}" class="transition hover:text-primary">About</a>
                    <a href="{{ route('faq') }}" class="transition hover:text-primary">FAQ</a>
                    <a href="{{ route('contact') }}" class="transition hover:text-primary">Contact</a>
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" class="nx-btn nx-btn--primary !py-2 !px-4">My Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden px-3 py-2 text-sm font-semibold text-slate-600 transition hover:text-primary sm:block dark:text-slate-300">Sign in</a>
                        <a href="{{ route('register') }}" class="nx-btn nx-btn--primary !py-2 !px-4">Get Started</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main>{{ $slot }}</main>

        {{-- Footer (Module 28: admin-assignable columns + legal via SiteChrome). --}}
        <x-site-footer variant="full" />
    </div>
</x-layouts.app>
