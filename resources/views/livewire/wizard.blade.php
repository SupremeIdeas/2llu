{{-- NaaraSim Wizard widget (roadmap §11). Floating, collapsible, glowing brand
     border, SVG icons only, dark-mode parity. Buttons-only state machine — fully
     usable with no LLM. Money actions disable while in flight (wire:loading). --}}
<div class="fixed bottom-6 right-4 z-50 print:hidden" wire:key="naara-wizard"
     @if ($this->otpPending) wire:poll.4s @endif>

    @if (! $open)
        {{-- Launcher — with a live-OTP badge when a code is on its way / ready. --}}
        @php $otp = $this->liveOtp; @endphp
        @if ($otp && $otp->status === 'completed')
            {{-- "Code ready" pill — the pushed OTP surfacing (roadmap §3.10). --}}
            <button type="button" wire:click="openOtp"
                    class="mb-2 flex items-center gap-2 rounded-full bg-accent px-4 py-3 text-sm font-semibold text-navy shadow-lg shadow-accent/40 transition hover:brightness-105 motion-safe:animate-[naaraGlow_2.2s_ease-in-out_infinite]">
                <x-icon name="check" class="h-4 w-4" /> Your code is ready
            </button>
        @endif
        <button type="button" wire:click="toggle"
                aria-label="Open the NaaraSim helper"
                class="group relative flex items-center gap-2 rounded-full bg-primary px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark motion-safe:animate-[naaraGlow_2.8s_ease-in-out_infinite]">
            <x-icon name="zap" class="h-5 w-5" />
            <span class="hidden sm:inline">Ask NaaraSim</span>
            @if ($otp)
                <span class="absolute -right-1 -top-1 flex h-3.5 w-3.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-accent opacity-75 motion-safe:animate-ping"></span>
                    <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-accent ring-2 ring-white dark:ring-[#101d33]"></span>
                </span>
            @endif
        </button>
    @else
        {{-- Panel with an animated glowing brand border (reduced-motion → static). --}}
        <div class="relative w-[22rem] max-w-[calc(100vw-2rem)] rounded-2xl p-[2px] shadow-2xl
                    motion-safe:animate-[naaraGlow_3.5s_ease-in-out_infinite]
                    bg-gradient-to-br from-primary via-accent to-primary">
            <div class="flex max-h-[70vh] flex-col overflow-hidden rounded-[calc(1rem-1px)] bg-white dark:bg-[#101d33]">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-[#22314e]">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                            <x-icon name="zap" class="h-4 w-4" />
                        </span>
                        <span class="text-sm font-bold text-slate-900 dark:text-slate-100">NaaraSim Helper</span>
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($step !== 'purpose')
                            <button type="button" wire:click="restart" aria-label="Start over"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#22314e] dark:hover:text-slate-200">
                                <x-icon name="refresh" class="h-4 w-4" />
                            </button>
                        @endif
                        <button type="button" wire:click="toggle" aria-label="Minimise the helper"
                                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#22314e] dark:hover:text-slate-200">
                            <x-icon name="x" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4">

                    @if ($error)
                        <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                        </div>
                    @endif

                    {{-- Live-OTP nudge when the user is on another step (roadmap §3.10). --}}
                    @if ($step !== 'otp' && ($banner = $this->liveOtp) && $banner->status === 'completed')
                        <button type="button" wire:click="openOtp"
                                class="flex w-full items-center justify-between gap-2 rounded-lg bg-accent/15 px-3 py-2 text-left text-xs font-semibold text-navy dark:bg-accent/20 dark:text-accent">
                            <span class="flex items-center gap-1.5"><x-icon name="check" class="h-4 w-4" /> Your verification code arrived</span>
                            <x-icon name="chevron-right" class="h-4 w-4" />
                        </button>
                    @endif

                    {{-- 1) Purpose --}}
                    @if ($step === 'purpose')
                        <p class="text-sm text-slate-600 dark:text-slate-300">Hi! What would you like to do?</p>
                        @forelse ($this->purposes as $p)
                            <button type="button" wire:click="choosePurpose('{{ $p['key'] }}')" wire:key="purpose-{{ $p['key'] }}"
                                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-primary hover:bg-primary/5 dark:border-[#2D4060] dark:bg-[#182742] dark:hover:border-primary">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary dark:bg-primary/20">
                                    <x-icon name="{{ $p['icon'] }}" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $p['purpose'] }}</span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $p['name'] }} · {{ $p['tagline'] }}</span>
                                </span>
                            </button>
                        @empty
                            <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500 dark:bg-[#182742] dark:text-slate-400">
                                Our connectivity options are being set up — please check back shortly.
                            </p>
                        @endforelse

                    {{-- 2) Country --}}
                    @elseif ($step === 'country')
                        <p class="text-sm text-slate-600 dark:text-slate-300">Which country?</p>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach ($countries as $slug => $label)
                                <button type="button" wire:click="chooseCountry('{{ $slug }}')" wire:key="country-{{ $slug }}"
                                        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-sm text-slate-800 transition hover:border-primary hover:bg-primary/5 dark:border-[#2D4060] dark:bg-[#182742] dark:text-slate-100 dark:hover:border-primary">
                                    <x-country-flag :country="$slug" class="h-4 w-6 shrink-0" wire:key="wflag-{{ $slug }}" />
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                    {{-- 3a) Service (OTP / rental) --}}
                    @elseif ($step === 'service')
                        <p class="text-sm text-slate-600 dark:text-slate-300">Which service is the number for?</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($services as $svc)
                                <button type="button" wire:click="chooseService('{{ $svc }}')" wire:key="svc-{{ $svc }}"
                                        wire:loading.attr="disabled"
                                        class="flex flex-col items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-3 text-xs font-medium text-slate-700 transition hover:border-primary hover:bg-primary/5 disabled:opacity-50 dark:border-[#2D4060] dark:bg-[#182742] dark:text-slate-200 dark:hover:border-primary">
                                    <x-service-icon :slug="$svc" class="h-5 w-5" />
                                    {{ ucfirst($svc) }}
                                </button>
                            @endforeach
                        </div>
                        <div wire:loading wire:target="chooseService" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <x-icon name="refresh" class="h-4 w-4 animate-spin text-primary" /> Checking availability…
                        </div>

                    {{-- 3b) Device check (eSIM) --}}
                    @elseif ($step === 'device')
                        <p class="text-sm text-slate-600 dark:text-slate-300">Let’s make sure your phone supports eSIM.</p>
                        <input type="text" wire:model="device" placeholder="e.g. iPhone 14, Galaxy S22"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <button type="button" wire:click="checkDevice" wire:loading.attr="disabled"
                                class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                            <x-icon name="search" class="h-4 w-4" /> Check my device
                        </button>
                        @if ($deviceResult === true)
                            <div class="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-xs text-green-700 dark:bg-green-950/40 dark:text-green-300">
                                <x-icon name="check" class="h-4 w-4 shrink-0" /> Your device supports eSIM.
                            </div>
                        @elseif ($deviceResult === false)
                            <div class="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                <x-icon name="x" class="h-4 w-4 shrink-0" /> This device can’t use an eSIM.
                            </div>
                        @elseif ($deviceResult === null && $device !== '')
                            <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                {{ \App\Support\Niche\DeviceCompat::howToCheck() }}
                            </div>
                        @endif
                        <button type="button" wire:click="goToEsims"
                                @if ($deviceResult === false) disabled @endif
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50">
                            Browse eSIM plans <x-icon name="chevron-right" class="h-4 w-4" />
                        </button>

                    {{-- 3c) Pick a permanent number --}}
                    {{-- 3c-i) Number matching (Naara Line only, roadmap §5) --}}
                    @elseif ($step === 'match')
                        <p class="text-sm text-slate-600 dark:text-slate-300">Want a number with certain digits? Type a few (e.g. from your own number) and we’ll find the closest.</p>
                        <input type="text" wire:model="matchDigits" inputmode="numeric" placeholder="e.g. 1234"
                               wire:keydown.enter="findNumbers"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="$set('matchPosition', 'ends')"
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold transition {{ $matchPosition === 'ends' ? 'border-primary bg-primary/10 text-primary-dark dark:text-primary' : 'border-slate-200 text-slate-600 dark:border-[#2D4060] dark:text-slate-300' }}">
                                Ends with
                            </button>
                            <button type="button" wire:click="$set('matchPosition', 'contains')"
                                    class="rounded-lg border px-3 py-2 text-xs font-semibold transition {{ $matchPosition === 'contains' ? 'border-primary bg-primary/10 text-primary-dark dark:text-primary' : 'border-slate-200 text-slate-600 dark:border-[#2D4060] dark:text-slate-300' }}">
                                Contains
                            </button>
                        </div>
                        <p class="text-[11px] leading-relaxed text-slate-400">The match is on the last few digits — the country code will differ from your own number.</p>
                        <button type="button" wire:click="findNumbers" wire:loading.attr="disabled" wire:target="findNumbers"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <x-icon name="search" wire:loading.remove wire:target="findNumbers" class="h-4 w-4" />
                            <x-icon name="refresh" wire:loading wire:target="findNumbers" class="h-4 w-4 animate-spin" />
                            Find matching numbers
                        </button>
                        <button type="button" wire:click="showAnyNumber" wire:loading.attr="disabled" wire:target="showAnyNumber"
                                class="w-full rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                            Show any number
                        </button>

                    @elseif ($step === 'pick')
                        @if ($matchUsed && $candidates !== [])
                            <p class="text-sm text-slate-600 dark:text-slate-300">Closest matches to <span class="font-semibold">{{ $matchDigits }}</span>:</p>
                        @elseif ($candidates !== [])
                            <p class="text-sm text-slate-600 dark:text-slate-300">Pick your new permanent number:</p>
                        @endif
                        @foreach ($candidates as $c)
                            <button type="button" wire:click="provisionPermanent('{{ $c['number'] }}')" wire:key="cand-{{ $c['number'] }}"
                                    wire:loading.attr="disabled" wire:target="provisionPermanent"
                                    class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left transition hover:border-primary hover:bg-primary/5 disabled:opacity-50 dark:border-[#2D4060] dark:bg-[#182742] dark:hover:border-primary">
                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    <x-icon name="phone" class="h-4 w-4 text-primary" /> {{ $c['number'] }}
                                </span>
                                <span class="text-xs font-semibold text-primary-dark dark:text-primary">${{ number_format($c['monthly_retail'], 2) }}/mo</span>
                            </button>
                        @endforeach
                        @if ($candidates === [])
                            <div class="grid grid-cols-1 gap-2">
                                <button type="button" wire:click="showAnyNumber"
                                        class="rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Show any number</button>
                                <button type="button" wire:click="$set('step', 'country')"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">Try another country</button>
                            </div>
                        @endif
                        <div wire:loading wire:target="provisionPermanent" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <x-icon name="refresh" class="h-4 w-4 animate-spin text-primary" /> Activating your number…
                        </div>

                    {{-- 4) Review (OTP / rental) --}}
                    @elseif ($step === 'review')
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-[#182742]">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Service</span>
                                <span class="inline-flex items-center gap-1.5 font-semibold text-slate-900 dark:text-slate-100">
                                    <x-service-icon :slug="$service" class="h-4 w-4" /> {{ ucfirst($service) }}
                                </span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Price</span>
                                <span class="text-lg font-bold text-primary-dark dark:text-primary">${{ number_format($quoteRetail, 2) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-slate-400">
                                <span>Wallet balance</span><span>${{ number_format($balance, 2) }}</span>
                            </div>
                        </div>
                        <button type="button" wire:click="purchase" wire:loading.attr="disabled" wire:target="purchase"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                            <x-icon name="refresh" wire:loading wire:target="purchase" class="h-4 w-4 animate-spin" />
                            <span wire:loading.remove wire:target="purchase">Get this number</span>
                            <span wire:loading wire:target="purchase">Reserving…</span>
                        </button>

                    {{-- Top-up --}}
                    @elseif ($step === 'topup')
                        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Not enough wallet balance — you were <span class="font-semibold">not charged</span>. Top up, then come back;
                            your progress is saved.
                        </div>
                        <a href="{{ route('wallet') }}" wire:navigate
                           class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                            <x-icon name="wallet" class="h-4 w-4" /> Top up wallet
                        </a>

                    {{-- Result --}}
                    @elseif ($step === 'result')
                        @if ($notice)
                            <div class="flex items-start gap-2 rounded-lg bg-green-50 p-3 text-xs text-green-700 dark:bg-green-950/40 dark:text-green-300">
                                <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $notice }}</span>
                            </div>
                        @endif

                        @if ($order)
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-[#182742]" @if ($order->status === 'waiting') wire:poll.3s @endif>
                                <div class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                                    <x-icon name="phone" class="h-4 w-4 text-primary" /> {{ $order->phone_number }}
                                </div>
                                <div class="mt-3 rounded-lg bg-white p-3 text-center dark:bg-[#243352]">
                                    @if ($order->status === 'completed' && $order->otp_code)
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Your code</div>
                                        <div class="mt-1 text-2xl font-bold tracking-widest text-primary">{{ $order->otp_code }}</div>
                                    @elseif ($order->status === 'timeout')
                                        <div class="text-xs text-amber-600 dark:text-amber-400">No code arrived — your wallet was refunded.</div>
                                    @else
                                        <div class="flex items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <x-icon name="refresh" class="h-4 w-4 animate-spin text-primary" /> Waiting for your code…
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($vnumber)
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-[#182742]">
                                <div class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                                    <x-icon name="phone" class="h-4 w-4 text-primary" /> {{ $vnumber->phone_number }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Permanent · voice + SMS · renews monthly</div>
                            </div>
                        @endif

                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                            View on dashboard
                        </a>
                        <button type="button" wire:click="restart"
                                class="w-full rounded-lg px-3 py-2 text-sm font-medium text-primary hover:underline">
                            Do something else
                        </button>

                    {{-- OTP surface — the pushed code with one-tap copy (roadmap §3.10) --}}
                    @elseif ($step === 'otp')
                        @php $otp = $this->liveOtp; @endphp
                        @if (! $otp)
                            <p class="text-sm text-slate-500 dark:text-slate-400">No active code right now.</p>
                            <button type="button" wire:click="restart"
                                    class="w-full rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">Start over</button>
                        @else
                            <div class="rounded-xl bg-slate-50 p-4 dark:bg-[#182742]">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        <x-service-icon :slug="$otp->service_name" class="h-4 w-4" /> {{ ucfirst($otp->service_name) }}
                                    </span>
                                    <span class="text-xs text-slate-400">{{ $otp->phone_number }}</span>
                                </div>

                                <div class="mt-3 rounded-lg bg-white p-4 text-center dark:bg-[#243352]">
                                    @if ($otp->status === 'completed' && $otp->otp_code)
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Your code</div>
                                        {{-- One-tap copy (Alpine + clipboard); resets the label after 2s. --}}
                                        <div x-data="{ copied: false, code: @js($otp->otp_code) }" class="mt-1">
                                            <button type="button"
                                                    x-on:click="navigator.clipboard.writeText(code).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                    class="group inline-flex items-center gap-2 text-3xl font-bold tracking-widest text-primary transition hover:text-primary-dark"
                                                    aria-label="Copy code">
                                                <span>{{ $otp->otp_code }}</span>
                                                <x-icon name="copy" class="h-5 w-5 opacity-50 group-hover:opacity-100" />
                                            </button>
                                            <div class="mt-1 h-4 text-xs font-medium text-green-600 dark:text-green-400"
                                                 x-show="copied" x-transition x-cloak>Copied to clipboard</div>
                                            <div class="mt-1 h-4 text-xs text-slate-400" x-show="!copied">Tap the code to copy</div>
                                        </div>
                                    @elseif ($otp->status === 'timeout')
                                        <div class="text-xs text-amber-600 dark:text-amber-400">No code arrived in time — your wallet was refunded.</div>
                                    @else
                                        <div class="flex items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                            <x-icon name="refresh" class="h-4 w-4 animate-spin text-primary" /> Waiting for your code…
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="anotherOtp"
                                        class="flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                                    <x-icon name="refresh" class="h-4 w-4" /> Another code
                                </button>
                                <button type="button" wire:click="dismissOtp"
                                        class="flex items-center justify-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                                    <x-icon name="check" class="h-4 w-4" /> Done
                                </button>
                            </div>
                            <a href="{{ route('dashboard') }}" wire:navigate
                               class="block text-center text-xs font-medium text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">View on dashboard</a>
                        @endif
                    @endif
                </div>

                {{-- Footer: back --}}
                @if (! in_array($step, ['purpose', 'result', 'otp']))
                    <div class="border-t border-slate-100 px-4 py-2.5 dark:border-[#22314e]">
                        <button type="button" wire:click="back"
                                class="text-xs font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                            ← Back
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>

@once
    @push('head')
        <style>
            @keyframes naaraGlow {
                0%, 100% { box-shadow: 0 0 0 0 rgb(var(--brand-primary) / 0.35), 0 10px 25px -5px rgb(var(--brand-primary) / 0.25); }
                50%      { box-shadow: 0 0 0 6px rgb(var(--brand-primary) / 0.0), 0 10px 30px -5px rgb(var(--brand-accent) / 0.35); }
            }
        </style>
    @endpush
@endonce
