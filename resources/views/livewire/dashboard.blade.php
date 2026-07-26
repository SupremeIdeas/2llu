<div>
    {{-- Greeting + fact of the day (owner request): welcome by name, ask about
         their day, and teach what a NaaraSim number/eSIM can do worldwide.
         Premium hero (owner request): when an admin has uploaded hero art it
         sits behind the greeting under a gradient overlay, theme-switched and
         adding no extra height; otherwise the default gradient card shows. --}}
    @php($heroLight = \App\Support\HeroBackground::light())
    @php($heroDark = \App\Support\HeroBackground::dark())
    @php($hasHero = \App\Support\HeroBackground::isSet())
    <div class="relative mb-6 overflow-hidden rounded-2xl border p-5 {{ $hasHero ? 'border-slate-200/60 dark:border-white/10' : 'border-primary/15 bg-gradient-to-br from-primary/[0.07] via-transparent to-accent/[0.06] dark:border-primary/25 dark:from-primary/15 dark:to-accent/10' }}">
        @if ($hasHero)
            {{-- Background art layer (light + dark; one falls back to the other). --}}
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                <img src="{{ $heroLight ?: $heroDark }}" alt="" loading="lazy" decoding="async"
                     class="absolute inset-0 h-full w-full object-cover object-center {{ $heroDark ? 'dark:hidden' : '' }}">
                @if ($heroDark)
                    <img src="{{ $heroDark }}" alt="" loading="lazy" decoding="async"
                         class="absolute inset-0 hidden h-full w-full object-cover object-center dark:block">
                @endif
                {{-- Gradient overlay: image emerges at the top, solid surface where
                     the text sits, so the heading always reads cleanly. --}}
                <div class="absolute inset-0 bg-gradient-to-r from-white via-white/85 to-white/40 dark:from-[#0D1B2A] dark:via-[#0D1B2A]/85 dark:to-[#0D1B2A]/40"></div>
            </div>
        @endif
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                <x-icon name="signal" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $greeting }} <span class="font-normal text-slate-500 dark:text-slate-400">— {{ $greetingAsk }}</span></p>
                <p class="mt-1 flex items-start gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                    <x-icon name="zap" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <span><span class="font-semibold text-slate-700 dark:text-slate-200">Did you know?</span> {{ $factOfTheDay }}</span>
                </p>
            </div>
        </div>
    </div>

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

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Connectivity</h1>
        <div class="flex gap-2">
            <a href="{{ route('catalogue') }}" class="nx-btn nx-btn--primary !px-4 !py-2">
                <x-icon name="globe" class="h-4 w-4" /> Buy eSIM
            </a>
            <a href="{{ route('numbers') }}" class="nx-btn nx-btn--ghost !px-4 !py-2">
                <x-icon name="hash" class="h-4 w-4" /> Get number
            </a>
        </div>
    </div>

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
        <div class="mb-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['globe', 'eSIM Data Plans', 'Local data in 190+ countries — installed before you fly, connected when you land.', route('catalogue'), 'Browse plans'],
                ['signal', 'Naara Connect', 'Full eSIM — calls, SMS and data on one eSIM, with its own number.', route('catalogue', ['tab' => 'full']), 'See Full eSIMs'],
                ['hash', 'Verification Numbers', 'Receive one-time codes for WhatsApp, Google, Facebook and more — in seconds.', route('numbers'), 'Get a number'],
                ['phone', 'Virtual Numbers', 'A permanent second line for calls and SMS, without a second phone.', route('numbers'), 'Explore numbers'],
            ] as [$icon, $title, $text, $url, $cta])
                <a href="{{ $url }}" class="nx-card3d group block">
                    <div class="nx-card3d__body">
                        <span class="nx-card3d__glass" aria-hidden="true"></span>
                        <span class="nx-card3d__icon">
                            <x-icon :name="$icon" class="h-6 w-6" />
                        </span>
                        <h3 class="relative mt-5 font-display text-lg font-bold text-slate-900 dark:text-white">{{ $title }}</h3>
                        <p class="relative mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $text }}</p>
                        <span class="relative mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary dark:text-teal-300">
                            {{ $cta }} <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="package" class="h-4 w-4" /> eSIMs
            </h2>
            <div class="space-y-3">
                @forelse ($esimsActive as $esim)
                    <div wire:key="esim-{{ $esim->id }}" x-data="{ setup: false }" class="nx-card !p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex min-w-0 items-center gap-2.5">
                                @foreach (array_slice($esim->plan?->countries ?? [], 0, 3) as $iso)
                                    <x-country-flag :country="$iso" class="h-4 w-6 shrink-0" />
                                @endforeach
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-slate-900 dark:text-slate-100">{{ $esim->plan?->name ?? 'eSIM' }}</span>
                                    <x-model-badge :esim="true" class="mt-0.5" />
                                </span>
                            </span>
                            <x-ui.tag :variant="$esim->status === 'active' ? 'live' : (in_array($esim->status, ['pending', 'processing']) ? 'gold' : 'soon')">
                                {{ ucfirst($esim->status) }}
                            </x-ui.tag>
                        </div>

                        {{-- Data-remaining meter --}}
                        @if ($esim->data_remaining_mb !== null && $esim->plan?->data_mb)
                            @php($pct = max(0, min(100, (int) round($esim->data_remaining_mb / $esim->plan->data_mb * 100))))
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span>{{ number_format($esim->data_remaining_mb / 1024, 1) }} GB left</span>
                                    <span>{{ $pct }}%</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-[#243352]">
                                    <div class="h-full rounded-full {{ $pct > 20 ? 'bg-primary' : 'bg-action' }} transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endif

                        @if ($esim->iccid)
                            <div class="mt-2 text-xs text-slate-400 dark:text-slate-500">ICCID {{ $esim->iccid }}</div>
                        @endif

                        @if ($esim->qr_code_url || $esim->lpa_string)
                            <button type="button" @click="setup = ! setup" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                                <x-icon name="wifi" class="h-3.5 w-3.5" /> <span x-text="setup ? 'Hide setup' : 'Show setup'"></span>
                            </button>

                            <div x-show="setup" x-cloak class="mt-3 space-y-3 border-t border-slate-100 pt-3 dark:border-[#243352]">
                                @if ($esim->qr_code_url)
                                    <div class="flex flex-col items-center">
                                        <img src="{{ $esim->qr_code_url }}" alt="eSIM QR code" class="h-40 w-40 rounded-lg border border-slate-200 bg-white p-1 dark:border-[#2D4060]">
                                        <span class="mt-1 text-xs text-slate-400">Scan to install</span>
                                    </div>
                                @endif

                                {{-- Manual LPA fallback — shown beside every QR (Section 32) --}}
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Can’t scan? Add it manually:</p>
                                    @if ($esim->lpa_string)
                                        <div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
                                            <code class="min-w-0 flex-1 break-all rounded-lg bg-slate-100 px-2 py-1.5 font-mono text-[11px] text-slate-800 dark:bg-[#243352] dark:text-slate-200">{{ $esim->lpa_string }}</code>
                                            <button type="button" @click="navigator.clipboard.writeText(@js($esim->lpa_string)); copied = true; setTimeout(() => copied = false, 1500)"
                                                    class="shrink-0 rounded-lg border border-slate-300 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]" aria-label="Copy activation code">
                                                <x-icon name="copy" class="h-4 w-4" x-show="! copied" />
                                                <x-icon name="check" class="h-4 w-4 text-green-500" x-show="copied" x-cloak />
                                            </button>
                                        </div>
                                    @else
                                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">The manual activation code will appear here once your eSIM finishes provisioning.</p>
                                    @endif
                                    <ul class="mt-2 space-y-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                        @foreach (\App\Support\Niche\LpaActivation::steps() as $step)
                                            <li>• {{ $step }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    @if ($esimsArchived->isEmpty())
                        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                            No eSIMs yet. <a href="{{ route('catalogue') }}" class="text-primary hover:underline">Browse plans</a>.
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- Archive: expired eSIMs, tucked away so the active view stays clean. --}}
            @if ($esimsArchived->isNotEmpty())
                <div x-data="{ open: false }" class="mt-3">
                    <button type="button" @click="open = ! open" class="flex w-full items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-400 dark:hover:bg-[#243352]">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="package" class="h-3.5 w-3.5" /> Archive ({{ $esimsArchived->count() }} expired)</span>
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                    </button>
                    <div x-show="open" x-cloak class="mt-2 space-y-2">
                        @foreach ($esimsArchived as $esim)
                            <div wire:key="esim-arch-{{ $esim->id }}" class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-[#243352]/60">
                                <span class="min-w-0">
                                    <span class="block truncate text-slate-600 dark:text-slate-300">{{ $esim->plan?->name ?? 'eSIM' }}</span>
                                    <x-model-badge :esim="true" class="mt-0.5 opacity-70" />
                                </span>
                                <span class="shrink-0 text-xs font-medium text-slate-400">Expired</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="hash" class="h-4 w-4" /> Numbers
            </h2>

            @forelse ($numberGroups as $group)
                {{-- Each Model is its own tidy group — no mixing of OTP / rental / permanent. --}}
                <div class="mb-4">
                    <div class="mb-2 flex items-center gap-2">
                        <x-model-badge :provider="null" :type="collect($group['model']['caps'])->contains('permanent') ? 'permanent' : (collect($group['model']['caps'])->contains('rental') ? 'rental' : 'otp')" />
                        <span class="text-[11px] text-slate-400">{{ $group['model']['tagline'] }}</span>
                    </div>
                    <div class="space-y-3">
                        @foreach ($group['items'] as $number)
                            <div wire:key="num-{{ $number->id }}" class="nx-card !p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300">
                                            <x-service-icon :slug="$number->service_name ?? 'sms'" class="h-5 w-5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-slate-900 dark:text-slate-100">{{ $number->phone_number ?? ucfirst($number->service_name) }}</span>
                                            <span class="block text-xs capitalize text-slate-400">{{ $number->service_name }}</span>
                                        </span>
                                    </span>
                                    <div class="flex flex-col items-end gap-1">
                                        <x-ui.tag :variant="in_array($number->status, ['completed', 'active']) ? 'live' : ($number->status === 'waiting' ? 'gold' : 'soon')">
                                            {{ ucfirst($number->status) }}
                                        </x-ui.tag>
                                        @if ($number->otp_code)
                                            <span class="rounded-lg bg-primary/10 px-2 py-0.5 font-mono text-sm font-bold tracking-widest text-primary dark:bg-primary/20 dark:text-teal-300">{{ $number->otp_code }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                @if ($numbersArchived->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                        No numbers yet. <a href="{{ route('numbers') }}" class="text-primary hover:underline">Get one</a>.
                    </div>
                @endif
            @endforelse

            {{-- Archive: cancelled / expired / refunded numbers. --}}
            @if ($numbersArchived->isNotEmpty())
                <div x-data="{ open: false }" class="mt-1">
                    <button type="button" @click="open = ! open" class="flex w-full items-center justify-between rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-400 dark:hover:bg-[#243352]">
                        <span class="inline-flex items-center gap-1.5"><x-icon name="hash" class="h-3.5 w-3.5" /> Archive ({{ $numbersArchived->count() }})</span>
                        <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform" ::class="open && 'rotate-90'" />
                    </button>
                    <div x-show="open" x-cloak class="mt-2 space-y-2">
                        @foreach ($numbersArchived as $number)
                            <div wire:key="num-arch-{{ $number->id }}" class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-[#243352]/60">
                                <span class="min-w-0">
                                    <span class="block truncate text-slate-600 dark:text-slate-300">{{ $number->phone_number ?? ucfirst($number->service_name) }}</span>
                                    <span class="mt-0.5 flex items-center gap-1.5">
                                        <span class="text-xs capitalize text-slate-400">{{ $number->service_name }}</span>
                                        <x-model-badge :type="$number->type" :provider="$number->provider" class="opacity-70" />
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs font-medium capitalize text-slate-400">{{ $number->status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
