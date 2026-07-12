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
                    <div wire:key="esim-{{ $esim->id }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
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
