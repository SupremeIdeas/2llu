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

        {{-- Footer --}}
        <footer class="border-t border-white/10 bg-navy text-slate-300">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 md:grid-cols-4">
                <div class="md:col-span-2">
                    <x-brand-logo variant="product" class="h-9 max-w-[170px]" />
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-slate-400">
                        {{ \App\Support\BrandSettings::name() }} — Stay Connected. No Borders. No Swaps.
                        Premium eSIM connectivity for African travelers and global professionals.
                        190+ countries, instant activation, no roaming surprises.
                    </p>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-accent">Product</p>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('how-it-works') }}" class="transition hover:text-white">How It Works</a></li>
                        <li><a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="transition hover:text-white">Browse Plans</a></li>
                        <li><a href="{{ route('how-it-works') }}#compatibility" class="transition hover:text-white">Device Compatibility</a></li>
                        <li><a href="{{ route('faq') }}" class="transition hover:text-white">Help Center</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-accent">Company</p>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('about') }}" class="transition hover:text-white">About Us</a></li>
                        <li><a href="{{ route('contact') }}" class="transition hover:text-white">Contact</a></li>
                        <li><a href="{{ route('refund-policy') }}" class="transition hover:text-white">Refund Policy</a></li>
                        <li><a href="{{ route('legal') }}" class="transition hover:text-white">Privacy &amp; Terms</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-slate-500 sm:flex-row">
                    <span>&copy; {{ date('Y') }} {{ \App\Support\BrandSettings::name() }}. A product of <span class="text-slate-300">Supreme Ideas Agency</span>. All rights reserved.</span>
                    <span class="flex items-center gap-4">
                        <a href="{{ route('legal') }}" class="transition hover:text-white">Terms</a>
                        <a href="{{ route('legal') }}" class="transition hover:text-white">Privacy</a>
                        <a href="{{ route('refund-policy') }}" class="transition hover:text-white">Refunds</a>
                    </span>
                </div>
            </div>
        </footer>
    </div>
</x-layouts.app>
