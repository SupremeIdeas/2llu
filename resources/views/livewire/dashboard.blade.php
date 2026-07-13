<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">My Connectivity</h1>
        <div class="flex gap-2">
            <a href="{{ route('catalogue') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="globe" class="h-4 w-4" /> Buy eSIM
            </a>
            <a href="{{ route('numbers') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="hash" class="h-4 w-4" /> Get number
            </a>
        </div>
    </div>

    @if ($wallet)
        <div class="mb-6 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
            <x-icon name="wallet" class="h-4 w-4 text-primary" />
            <span class="text-slate-500 dark:text-slate-400">Wallet:</span>
            <span class="font-semibold text-slate-900 dark:text-slate-100">NGN {{ number_format((float) $wallet->ngn_balance, 2) }}</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span class="font-semibold text-slate-900 dark:text-slate-100">${{ number_format((float) $wallet->usd_balance, 2) }}</span>
            <a href="{{ route('wallet') }}" class="ml-auto text-primary hover:underline">Top up</a>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="package" class="h-4 w-4" /> eSIMs
            </h2>
            <div class="space-y-3">
                @forelse ($esims as $esim)
                    <div wire:key="esim-{{ $esim->id }}" x-data="{ setup: false }" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $esim->plan?->name ?? 'eSIM' }}</span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $esim->status === 'active',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => in_array($esim->status, ['pending', 'processing']),
                                'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => in_array($esim->status, ['expired', 'failed', 'cancelled']),
                            ])>{{ ucfirst($esim->status) }}</span>
                        </div>
                        @if ($esim->iccid)
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">ICCID {{ $esim->iccid }}</div>
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
                    <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                        No eSIMs yet. <a href="{{ route('catalogue') }}" class="text-primary hover:underline">Browse plans</a>.
                    </div>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="hash" class="h-4 w-4" /> Numbers
            </h2>
            <div class="space-y-3">
                @forelse ($numbers as $number)
                    <div wire:key="num-{{ $number->id }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $number->phone_number ?? ucfirst($number->service_name) }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-[#243352] dark:text-slate-400">{{ ucfirst($number->status) }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ ucfirst($number->service_name) }}@if ($number->otp_code) · code {{ $number->otp_code }} @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                        No numbers yet. <a href="{{ route('numbers') }}" class="text-primary hover:underline">Get one</a>.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
