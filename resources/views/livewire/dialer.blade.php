@php
    // iOS-style keypad: digit + the letters printed beneath it. '0' long-presses
    // to '+' (handled in Alpine below). '*' / '#' carry no letters.
    $keypad = [
        ['d' => '1', 'l' => ''],       ['d' => '2', 'l' => 'A B C'],  ['d' => '3', 'l' => 'D E F'],
        ['d' => '4', 'l' => 'G H I'],  ['d' => '5', 'l' => 'J K L'],  ['d' => '6', 'l' => 'M N O'],
        ['d' => '7', 'l' => 'P Q R S'],['d' => '8', 'l' => 'T U V'],  ['d' => '9', 'l' => 'W X Y Z'],
        ['d' => '*', 'l' => ''],       ['d' => '0', 'l' => '+'],      ['d' => '#', 'l' => ''],
    ];
@endphp

<div class="mx-auto max-w-lg" data-dialer data-token-url="{{ route('voice.token') }}">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Call abroad</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Dial any international number straight from your browser — no app, no second phone.
        You're charged per minute from your wallet, and unused minutes come straight back.
    </p>

    {{-- Nav: back to Numbers, and the contact book. --}}
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('numbers') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Numbers
        </a>
        <a href="{{ route('numbers.contacts') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
            <x-icon name="id-card" class="h-4 w-4" /> Contacts
        </a>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white px-5 py-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]"
         x-data="{
            hold: null, held: false,
            press(d) { $wire.destination = ($wire.destination || '') + d; },
            back() { $wire.destination = ($wire.destination || '').slice(0, -1); },
            clearAll() { $wire.destination = ''; },
            startZero() { this.held = false; this.hold = setTimeout(() => { this.held = true; this.press('+'); }, 400); },
            endZero() { clearTimeout(this.hold); if (! this.held) this.press('0'); },
            get valid() { return /^\+?[0-9]{6,15}$/.test(($wire.destination || '').trim()); }
         }">
        @if ($error)
            <div class="mb-4 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
            </div>
        @endif

        {{-- Number display — big, centred, editable (paste-friendly). --}}
        <div class="relative flex min-h-[3.25rem] items-center justify-center px-8">
            <input type="tel" wire:model.live="destination" inputmode="tel" placeholder="Enter a number"
                   class="w-full appearance-none border-0 bg-transparent p-0 text-center text-[1.75rem] font-semibold tracking-wide text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-600">
            <button type="button" x-show="($wire.destination || '').length" x-on:click="back()" x-on:contextmenu.prevent="clearAll()"
                    aria-label="Delete last digit"
                    class="absolute right-1 flex h-9 w-9 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 active:scale-90 dark:hover:bg-white/5">
                <x-icon name="delete" class="h-5 w-5" />
            </button>
        </div>

        {{-- Live quote (retail only — provider cost is never shown). --}}
        <div class="mt-2 flex min-h-[1.25rem] items-center justify-center">
            @if ($quoted && $ratePerMin !== null)
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-primary dark:text-teal-300">${{ number_format($ratePerMin, 2) }}</span>/min
                    · <x-icon name="wallet" class="mr-0.5 inline h-3.5 w-3.5" />{{ $fundedMinutes }} min funded
                </p>
            @else
                <button type="button" wire:click="prepare" wire:loading.attr="disabled" wire:target="prepare"
                        x-bind:disabled="! valid"
                        class="text-xs font-medium text-slate-400 transition hover:text-primary disabled:opacity-40 dark:hover:text-teal-300">
                    <span wire:loading.remove wire:target="prepare">Check the per-minute rate</span>
                    <span wire:loading wire:target="prepare" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3 w-3" /> Checking…</span>
                </button>
            @endif
        </div>

        {{-- iOS keypad --}}
        <div class="mx-auto mt-4 grid max-w-[19rem] grid-cols-3 gap-x-6 gap-y-3">
            @foreach ($keypad as $k)
                @if ($k['d'] === '0')
                    <button type="button" wire:key="key-0"
                            x-on:pointerdown.prevent="startZero()" x-on:pointerup="endZero()" x-on:pointerleave="clearTimeout(hold)"
                            class="group mx-auto flex h-16 w-16 flex-col items-center justify-center rounded-full bg-slate-100 leading-none transition active:scale-90 active:bg-slate-200 dark:bg-[#243352] dark:active:bg-[#2D4060]">
                        <span class="text-2xl font-semibold text-slate-800 dark:text-slate-100">0</span>
                        <span class="mt-0.5 text-[0.65rem] font-semibold tracking-widest text-slate-400">+</span>
                    </button>
                @else
                    <button type="button" wire:key="key-{{ $k['d'] }}" x-on:click="press('{{ $k['d'] }}')"
                            class="group mx-auto flex h-16 w-16 flex-col items-center justify-center rounded-full bg-slate-100 leading-none transition active:scale-90 active:bg-slate-200 dark:bg-[#243352] dark:active:bg-[#2D4060]">
                        <span class="text-2xl font-semibold text-slate-800 dark:text-slate-100">{{ $k['d'] }}</span>
                        @if ($k['l'] !== '')
                            <span class="mt-0.5 text-[0.6rem] font-semibold tracking-[0.2em] text-slate-400">{{ $k['l'] }}</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Big green call button (server validates + holds the wallet; the whole
             money path lives in dial()). --}}
        <div class="mt-6 flex items-center justify-center">
            <button type="button" wire:click="dial" wire:loading.attr="disabled" wire:target="dial"
                    x-bind:disabled="! valid"
                    aria-label="Call"
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-green-500 text-white shadow-lg shadow-green-500/30 transition hover:bg-green-600 active:scale-90 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none dark:disabled:bg-[#243352]">
                <span wire:loading.remove wire:target="dial"><x-icon name="phone" class="h-7 w-7" /></span>
                <span wire:loading wire:target="dial"><x-ui.spinner class="h-7 w-7" /></span>
            </button>
        </div>
        <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">
            We reserve your funded minutes before dialling and refund whatever you don't use.
        </p>
    </div>

    {{-- Contacts quick-pick (Part C) — tap a saved name to fill the field. --}}
    @if ($contacts->isNotEmpty())
        <div class="mt-6" x-data="{ fill(n) { $wire.destination = n; } }">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Your contacts</h2>
                <a href="{{ route('numbers.contacts') }}" wire:navigate class="text-xs font-medium text-primary hover:underline dark:text-teal-300">Manage</a>
            </div>
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($contacts as $contact)
                    <button type="button" wire:key="dc-{{ $contact->id }}"
                            x-on:click="fill('{{ $contact->phone_number }}')"
                            class="flex shrink-0 flex-col items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2.5 text-center transition hover:border-primary/40 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]">
                        @include('partials.contact-avatar', ['contact' => $contact, 'size' => 'h-9 w-9 text-xs'])
                        <span class="max-w-[4.5rem] truncate text-[11px] font-medium text-slate-600 dark:text-slate-300">{{ $contact->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ============================================================
         Live-call screen — a full-screen iOS-style overlay driven by
         dialer.js (shows/hides via the `hidden` class). Alpine owns only
         the in-call DTMF keypad's visibility; JS owns the audio + controls.
         ============================================================ --}}
    <div data-dialer-panel class="hidden fixed inset-0 z-[70] flex flex-col items-center justify-between
                bg-gradient-to-b from-[#0D1B2A] via-[#122338] to-[#0A1524] px-6 py-12 text-white"
         x-data="{ pad: false }">

        {{-- Callee identity --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-4" x-show="! pad" x-transition.opacity>
            <span data-dialer-avatar aria-hidden="true"
                  class="flex h-28 w-28 items-center justify-center rounded-full bg-gradient-to-br from-teal-400 to-teal-600 text-4xl font-bold text-white shadow-2xl shadow-black/40">
                <x-icon name="phone" class="h-12 w-12" data-dialer-avatar-glyph />
            </span>
            <div class="text-center">
                <p data-dialer-peer class="text-2xl font-semibold">—</p>
                <p data-dialer-peer-sub class="mt-0.5 text-sm text-white/50"></p>
            </div>
            <p class="flex items-center gap-2 text-sm text-white/70">
                <span data-dialer-state>Connecting…</span>
                <span data-dialer-timer class="font-mono tabular-nums">00:00</span>
            </p>
        </div>

        {{-- In-call DTMF keypad (send tones during the call). --}}
        <div class="flex flex-1 flex-col items-center justify-center" x-show="pad" x-cloak x-transition.opacity>
            <p class="mb-4 flex items-center gap-2 text-sm text-white/60">
                <span data-dialer-peer-mirror>—</span> · <span data-dialer-timer-mirror class="font-mono tabular-nums">00:00</span>
            </p>
            <div class="grid max-w-[17rem] grid-cols-3 gap-x-6 gap-y-3">
                @foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $d)
                    <button type="button" data-dialer-dtmf="{{ $d }}" wire:key="dtmf-{{ $d }}"
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-xl font-semibold text-white transition active:scale-90 active:bg-white/20">{{ $d }}</button>
                @endforeach
            </div>
            <button type="button" x-on:click="pad = false" class="mt-5 text-sm font-medium text-white/60 hover:text-white">Hide keypad</button>
        </div>

        {{-- Controls: mute · keypad · speaker --}}
        <div class="w-full max-w-xs shrink-0">
            <div class="mb-8 grid grid-cols-3 gap-4">
                <button type="button" data-dialer-mute aria-pressed="false"
                        class="nx-callctl flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="nx-callctl-btn flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition">
                        <x-icon name="mic" class="h-6 w-6 nx-callctl-on" /><x-icon name="mic-off" class="h-6 w-6 nx-callctl-off" />
                    </span>
                    <span class="nx-callctl-on">Mute</span><span class="nx-callctl-off">Muted</span>
                </button>

                <button type="button" x-on:click="pad = ! pad"
                        class="flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition" x-bind:class="pad ? 'bg-white/25' : ''">
                        <x-icon name="grid" class="h-6 w-6" />
                    </span>
                    Keypad
                </button>

                <button type="button" data-dialer-speaker aria-pressed="false"
                        class="nx-callctl flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="nx-callctl-btn flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition">
                        <x-icon name="volume-2" class="h-6 w-6" />
                    </span>
                    Speaker
                </button>
            </div>

            {{-- End call --}}
            <div class="flex justify-center">
                <button type="button" data-dialer-hangup aria-label="End call"
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white shadow-lg shadow-red-500/30 transition hover:bg-red-600 active:scale-90">
                    <x-icon name="phone" class="h-7 w-7 rotate-[135deg]" />
                </button>
            </div>
        </div>
    </div>

    {{-- Recent calls (retail totals only — never provider cost). --}}
    @if ($recent->isNotEmpty())
        <div class="mt-8">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Recent calls</h2>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-[#243352] dark:border-[#2D4060] dark:bg-[#1A2840]">
                @foreach ($recent as $call)
                    <div class="flex items-center justify-between px-4 py-3 text-sm" wire:key="call-{{ $call->id }}">
                        <div class="flex items-center gap-2">
                            <x-icon name="phone" class="h-4 w-4 text-slate-400" />
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $call->destination }}</span>
                        </div>
                        <div class="text-right">
                            @if ($call->status === 'completed' && (int) $call->minutes_billed > 0)
                                <span class="font-semibold text-slate-900 dark:text-slate-100">${{ number_format((float) $call->amount_charged, 2) }}</span>
                                <span class="text-xs text-slate-400"> · {{ $call->minutes_billed }} min</span>
                            @else
                                <span class="text-xs text-slate-400">No answer — refunded</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
