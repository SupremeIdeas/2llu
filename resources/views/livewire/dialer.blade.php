<div class="mx-auto max-w-lg" data-dialer data-token-url="{{ route('voice.token') }}">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Call abroad</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Dial any international number straight from your browser — no app, no second phone.
        You're charged per minute from your wallet, and unused minutes come straight back.
    </p>

    {{-- Back to the Numbers hub. --}}
    <a href="{{ route('numbers') }}" wire:navigate
       class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to Numbers
    </a>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]"
         x-data="{ press(d) { $wire.destination = ($wire.destination || '') + d; }, back() { $wire.destination = ($wire.destination || '').slice(0, -1); } }">
        @if ($error)
            <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
            </div>
        @endif

        {{-- Destination --}}
        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Number to call</label>
        <div class="flex items-center gap-2">
            <input type="tel" wire:model.live="destination" inputmode="tel" placeholder="+2348012345678"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-lg tracking-wide text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            <button type="button" x-on:click="back()" aria-label="Delete last digit"
                    class="shrink-0 rounded-lg border border-slate-300 p-2.5 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>

        {{-- Keypad --}}
        <div class="mt-4 grid grid-cols-3 gap-2">
            @foreach (['1','2','3','4','5','6','7','8','9','+','0','#'] as $key)
                <button type="button" wire:key="key-{{ $key }}" x-on:click="press('{{ $key }}')"
                        class="rounded-xl border border-slate-200 py-3 text-lg font-semibold text-slate-800 transition hover:border-primary/40 hover:bg-slate-50 active:scale-95 dark:border-[#2D4060] dark:text-slate-100 dark:hover:bg-[#243352]">
                    {{ $key }}
                </button>
            @endforeach
        </div>

        {{-- Live quote (retail only — provider cost is never shown). --}}
        @if ($quoted && $ratePerMin !== null)
            <div class="mt-4 flex items-center justify-between rounded-xl bg-primary/5 px-4 py-3 text-sm dark:bg-primary/10">
                <span class="text-slate-600 dark:text-slate-300">
                    <span class="font-semibold text-primary dark:text-teal-300">${{ number_format($ratePerMin, 2) }}</span>/min
                </span>
                <span class="text-slate-500 dark:text-slate-400">
                    <x-icon name="wallet" class="mr-1 inline h-4 w-4" />{{ $fundedMinutes }} min funded
                </span>
            </div>
        @endif

        {{-- Actions --}}
        <div class="mt-5 grid grid-cols-2 gap-3">
            <button type="button" wire:click="prepare" wire:loading.attr="disabled" wire:target="prepare"
                    class="flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <span wire:loading.remove wire:target="prepare" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4" /> Check rate</span>
                <span wire:loading wire:target="prepare" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Checking…</span>
            </button>
            <button type="button" wire:click="dial" wire:loading.attr="disabled" wire:target="dial"
                    @disabled(! $quoted || ($fundedMinutes ?? 0) < 1)
                    class="flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-50">
                <span wire:loading.remove wire:target="dial" class="inline-flex items-center gap-2"><x-icon name="phone" class="h-5 w-5" /> Call</span>
                <span wire:loading wire:target="dial" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-5 w-5 animate-spin" /> Connecting…</span>
            </button>
        </div>
        <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">
            We reserve your funded minutes before dialling and refund whatever you don't use.
        </p>
    </div>

    {{-- Live-call panel — shown by the dialer JS once a call is placed. --}}
    <div data-dialer-panel class="hidden fixed inset-x-0 bottom-0 z-50 mx-auto max-w-lg p-4">
        <div class="rounded-2xl border border-primary/30 bg-white p-5 shadow-2xl shadow-primary/20 dark:border-primary/40 dark:bg-[#1A2840]">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                    <x-icon name="phone" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p data-dialer-peer class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">—</p>
                    <p class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span data-dialer-state>Connecting…</span>
                        <span data-dialer-timer class="font-mono">00:00</span>
                    </p>
                </div>
                <button type="button" data-dialer-hangup aria-label="Hang up"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-red-500 text-white transition hover:bg-red-600">
                    <x-icon name="x" class="h-5 w-5" />
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
