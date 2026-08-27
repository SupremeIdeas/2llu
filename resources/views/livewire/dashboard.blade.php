<div>
    {{-- Greeting + fact of the day (owner request): welcome by name, ask about
         their day, and teach what a NaaraSim number/eSIM can do worldwide.
         Premium hero (owner request): when an admin has uploaded hero art it
         sits behind the greeting under a gradient overlay, theme-switched and
         adding no extra height; otherwise the default gradient card shows. --}}
    @php($heroLight = \App\Support\HeroBackground::light())
    @php($heroDark = \App\Support\HeroBackground::dark())
    @php($hasHero = \App\Support\HeroBackground::showsOnDashboard())
    @php($heroDesc = \App\Support\HeroBackground::description())
    @php($gAvatar = \App\Support\SupportSettings::avatar())
    @php($gName = \App\Support\SupportSettings::name())
    @php($greetingOn = \App\Support\SupportSettings::greetingEnabled())
    @php($greetingMode = \App\Support\SupportSettings::greetingMode())

    {{-- Dashboard home hero (BUILD-13): title, a short description, a REAL visible
         image (not a faded background), then the two primary CTAs in a strict
         two-column grid that never collapses to one column at any width. The
         whole block is kept compact — title/description one line each, the image
         capped to a short 2:1 band — so it all fits a 375×667 phone viewport on
         first paint. When no hero image is set it degrades cleanly to title +
         description + buttons in the same layout, no empty gap. --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Connectivity</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $heroDesc }}</p>

        @if ($hasHero)
            {{-- Fixed 2:1 aspect + max-height cap: any uploaded image renders as a
                 short, predictable band regardless of its natural proportions,
                 leaving room for the buttons within the first viewport. --}}
            <div class="mt-3 aspect-[2/1] max-h-52 w-full overflow-hidden rounded-2xl border border-slate-200/70 dark:border-white/10 sm:max-h-64">
                <img src="{{ $heroLight ?: $heroDark }}" alt="" loading="lazy" decoding="async"
                     class="h-full w-full object-cover object-center {{ $heroDark ? 'dark:hidden' : '' }}">
                @if ($heroDark)
                    <img src="{{ $heroDark }}" alt="" loading="lazy" decoding="async"
                         class="hidden h-full w-full object-cover object-center dark:block">
                @endif
            </div>
        @endif

        {{-- Bento action tiles — Buy eSIM / Get number sit side by side at EVERY
             screen width and never stack. Same bento style as the showcase +
             number-section cards; navigation targets unchanged. --}}
        <div class="mt-4 grid grid-cols-2 gap-3">
            <x-bento-tile bkey="buy-esim" label="Buy eSIM" variant="primary"
                          :href="route('catalogue')" wire:navigate />
            <x-bento-tile bkey="get-number" label="Get number"
                          :href="route('numbers')" wire:navigate />
        </div>
    </div>

    @if ($greetingOn)
        {{-- The greeting reads as a chat message from the assistant. A per-day
             dismiss key means the user can clear it for a cleaner home and it
             greets again tomorrow. Two admin-chosen styles: an inline card (can
             carry hero art behind it) or a floating, dismissable popup. --}}
        @php($greetKey = 'nx_greet_'.now()->format('Ymd'))
        @if ($greetingMode === 'popup')
            <div x-data="{ show: false }"
                 x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
                 x-show="show" x-cloak x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 class="fixed bottom-24 left-4 right-4 z-40 mx-auto max-w-sm sm:left-6 sm:right-auto lg:bottom-6">
                <div class="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-2xl shadow-primary/10 backdrop-blur-md dark:border-white/10 dark:bg-[#101d33]/90">
                    @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
                </div>
            </div>
        @else
            {{-- Greeting is now a plain gradient card. The hero art it used to
                 carry as a faded background moved up into the real image block
                 above (BUILD-13), so it isn't rendered twice. --}}
            <div x-data="{ show: true }"
                 x-init="$nextTick(() => { show = localStorage.getItem('{{ $greetKey }}') !== '1'; })"
                 x-show="show" x-cloak
                 class="relative mb-6 overflow-hidden rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/[0.07] via-transparent to-accent/[0.06] p-5 dark:border-primary/25 dark:from-primary/15 dark:to-accent/10">
                @include('partials.greeting-bubble', ['onDismiss' => "@click=\"show = false; localStorage.setItem('{$greetKey}', '1')\""])
            </div>
        @endif
    @endif

    {{-- Coupon marketing nudge (owner request): a friendly first-purchase /
         comeback discount, shown only to a not-yet-purchased account. --}}
    @if ($couponNudge)
        <div class="mb-6 overflow-hidden rounded-2xl border border-accent/30 bg-gradient-to-br from-accent/10 via-transparent to-primary/[0.06] p-5 dark:border-accent/40 dark:from-accent/15 dark:to-primary/10">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-accent dark:bg-accent/25">
                    <x-icon name="gift" class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-slate-900 dark:text-slate-100">{{ $couponNudge['title'] }}</p>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ $couponNudge['message'] }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-lg border border-dashed border-accent/50 bg-white px-3 py-1.5 font-mono text-sm font-bold tracking-wider text-accent dark:bg-[#1A2840]">{{ $couponNudge['code'] }}</span>
                    <a href="{{ route('catalogue') }}" wire:navigate class="nx-btn nx-btn--primary !px-4 !py-2 text-sm">
                        {{ $couponNudge['cta'] }} <x-icon name="chevron-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Promo banners (Module 31): admin-managed carousel, coupon chips included. --}}
    <x-banner-zone placement="dashboard_home" class="mb-8" />

    {{-- Premium wallet card (Module 27.5 — adapted from the finance-card picks):
         gradient brand card with both balances and a clear top-up action. --}}
    @if ($wallet)
        <a href="{{ route('wallet') }}"
           class="group relative mb-8 block overflow-hidden rounded-3xl bg-gradient-to-br from-primary via-primary-dark to-navy p-6 text-white shadow-xl shadow-primary/20 transition hover:shadow-2xl hover:shadow-primary/30 sm:p-7">
            <div class="pointer-events-none absolute -right-14 -top-14 h-48 w-48 rounded-full bg-accent/20 blur-3xl transition group-hover:bg-accent/30"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-44 w-44 rounded-full bg-white/10 blur-3xl"></div>

            <div class="relative flex items-start justify-between">
                <div>
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100">
                        <x-icon name="wallet" class="h-4 w-4" /> Wallet balance
                    </p>
                    <p class="mt-3 font-display text-4xl font-bold tracking-tight">
                        ${{ number_format((float) $wallet->usd_balance, 2) }}
                    </p>
                    <p class="mt-1 text-sm text-teal-100/90">NGN {{ number_format((float) $wallet->ngn_balance, 2) }}</p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur transition group-hover:bg-accent group-hover:text-navy">
                    Top up <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            </div>
            <p class="relative mt-5 text-[11px] tracking-wide text-teal-100/70">{{ auth()->user()->name }} · {{ \App\Support\BrandSettings::name() }}</p>
        </a>
    @endif

    {{-- First-visit value showcase (Module 32 pick — om_5409/chase2k25 3D glass
         cards, rebuilt on brand): the three product lines as floating glass
         cards with our SVG feature icons. --}}
    @unless ($hasAny)
        {{-- Each row carries its admin-managed bento icon key (App\Support\BentoIcons)
             so the 3D illustration + its opacity are changeable in Admin. --}}
        @php($showcase = [
            ['esim-data-plans', 'eSIM Data Plans', 'Local data in 190+ countries — installed before you fly, connected when you land.', route('catalogue'), 'Browse plans', null],
            ['naara-connect', 'Naara Connect', 'Full eSIM — calls, SMS and data on one eSIM, with its own number.', route('catalogue', ['tab' => 'full']), 'See Full eSIMs', null],
            ['verification-numbers', 'Verification Numbers', 'Receive one-time codes for WhatsApp, Google, Facebook and more — in seconds.', route('numbers'), 'Get a number', null],
            ['virtual-numbers', 'Virtual Numbers', 'A permanent second line for calls and SMS, without a second phone.', route('numbers'), 'Explore numbers', null],
        ])
        {{-- Naara Gift entry — flips from "Coming soon" to a live link the moment the API keys are added. --}}
        @if (\App\Support\FeatureFlags::adminEnabled('naara_gift'))
            @php($giftLive = \App\Support\FeatureFlags::configured('naara_gift'))
            @php($showcase[] = ['naara-gift', 'Naara Gift', 'Send gift cards for 1,000+ brands — delivered instantly by email or WhatsApp.', route('gift-cards'), $giftLive ? 'Browse gifts' : 'Coming soon', $giftLive ? null : 'Soon'])
        @endif
        <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($showcase as [$bkey, $title, $text, $url, $cta, $badge])
                @php($bicon = \App\Support\BentoIcons::icon($bkey))
                <a href="{{ $url }}" wire:navigate class="nx-card3d group block">
                    <div class="nx-card3d__body nx-card3d__body--bento">
                        <span class="nx-card3d__glass" aria-hidden="true"></span>
                        {{-- 3D icon as a background watermark, bottom-right, behind the
                             text. Opacity is admin-tunable; the card clips it to its
                             rounded corners (overflow-hidden on the body). --}}
                        @if ($bicon)
                            <img src="{{ $bicon }}" alt="" aria-hidden="true"
                                 class="nx-card3d__bg pointer-events-none absolute bottom-0 right-0 w-[38%] max-w-[128px] select-none object-contain"
                                 style="opacity: {{ \App\Support\BentoIcons::opacityFraction($bkey) }}; transform: scale({{ \App\Support\BentoIcons::scale($bkey) }}); transform-origin: bottom right;">
                        @endif
                        <h3 class="relative flex items-center gap-2 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $title }}
                            @if ($badge)<span class="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase text-accent-dark dark:text-accent">{{ $badge }}</span>@endif
                        </h3>
                        <p class="relative mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $text }}</p>
                        <span class="relative mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                            {{ $cta }} <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endunless

    {{-- Slim connectivity summary — the full management hub now lives on the
         dedicated My Lines page (route numbers.lines). Same ConnectivityHub
         source, so these counts always match what My Lines shows. --}}
    @if ($hasAny)
        <a href="{{ route('numbers.lines') }}" wire:navigate
           class="group block rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary/40 hover:shadow-md dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <x-icon name="signal" class="h-4 w-4" /> My Lines
                </h2>
                <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                    Manage all <x-icon name="chevron-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </span>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $esimActiveCount }}</div>
                    <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Active eSIMs</div>
                </div>
                <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                    <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $numberActiveCount }}</div>
                    <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Active numbers</div>
                </div>
                <div class="rounded-2xl bg-slate-50 py-3 dark:bg-[#243352]/60">
                    <div class="text-2xl font-bold text-slate-400 dark:text-slate-500">{{ $archivedCount }}</div>
                    <div class="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">Archived</div>
                </div>
            </div>
        </a>
    @endif
</div>
