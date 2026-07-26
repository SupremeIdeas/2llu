<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-slate-100">Wallet</h1>

        {{-- My Spending (Module 32 pick — Gidarx aurora balance card, made
             functional): real this-month figures + a 14-day spend sparkline. --}}
        <div class="nx-aurora mb-6">
            <span class="nx-aurora__glow nx-aurora__glow--1" aria-hidden="true"></span>
            <span class="nx-aurora__glow nx-aurora__glow--2" aria-hidden="true"></span>
            <div class="relative grid gap-6 sm:grid-cols-2">
                <div>
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-teal-100/90">
                        <x-icon name="signal" class="h-4 w-4" /> My spending — {{ now()->format('F') }}
                    </p>
                    <p class="mt-3 font-display text-4xl font-bold tracking-tight text-white">${{ number_format(max($spentUsd, 0), 2) }}</p>
                    <p class="mt-1 text-xs text-teal-100/80">spent on eSIMs &amp; numbers this month</p>

                    <dl class="mt-5 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="flex items-center gap-1.5 text-teal-100/80"><x-icon name="zap" class="h-3.5 w-3.5 text-accent" /> Topped up (USD)</dt>
                            <dd class="font-semibold text-white">${{ number_format($topupUsd, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="flex items-center gap-1.5 text-teal-100/80"><x-icon name="zap" class="h-3.5 w-3.5 text-accent" /> Topped up (NGN)</dt>
                            <dd class="font-semibold text-white">NGN {{ number_format($topupNgn, 2) }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="flex flex-col justify-end">
                    <p class="mb-2 text-right text-[11px] font-medium uppercase tracking-wider text-teal-100/70">Last 14 days</p>
                    <div class="rounded-2xl bg-white/10 p-3 backdrop-blur">
                        @if ($hasSpendData)
                            <svg viewBox="0 0 200 48" class="h-16 w-full" role="img" aria-label="Daily spending, last 14 days" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="nx-spark-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#D4A017" stop-opacity="0.55" />
                                        <stop offset="100%" stop-color="#D4A017" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <polygon points="0,44 {{ $sparkline }} 200,44" fill="url(#nx-spark-fill)" />
                                <polyline points="{{ $sparkline }}" fill="none" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        @else
                            <div class="flex h-16 items-center justify-center gap-2 text-xs text-teal-100/70">
                                <x-icon name="signal" class="h-4 w-4" /> Your spending chart appears after your first purchase.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Balances --}}
        {{-- Display-currency switcher (owner request). USD stays the settlement
             currency; this only changes what prices are SHOWN in. --}}
        <div class="mb-3 flex items-center justify-end gap-2">
            <span class="text-xs text-slate-400 dark:text-slate-500">Show prices in</span>
            <div class="relative" x-data="{ open: false }">
                <button type="button" x-on:click="open = !open" x-on:click.outside="open = false"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-200">
                    <x-icon name="globe" class="h-3.5 w-3.5" /> {{ $displayCurrency }}
                    <x-icon name="chevron-right" class="h-3 w-3 rotate-90" />
                </button>
                <div x-show="open" x-cloak x-transition
                     class="absolute right-0 z-20 mt-1 max-h-64 w-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-[#2D4060] dark:bg-[#1A2840]">
                    @foreach ($currencyOptions as $code => $meta)
                        <button type="button" wire:key="cur-{{ $code }}" x-on:click="open = false" wire:click="setCurrency('{{ $code }}')"
                                @class([
                                    'flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-slate-50 dark:hover:bg-[#243352]',
                                    'font-bold text-primary dark:text-teal-300' => $displayCurrency === $code,
                                    'text-slate-600 dark:text-slate-300' => $displayCurrency !== $code,
                                ])>
                            <span>{{ $meta[1] }}</span>
                            <span class="text-slate-400">{{ $meta[0] }} {{ $code }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <x-icon name="wallet" class="h-4 w-4" /> NGN balance
                </div>
                <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">NGN {{ number_format((float) $wallet->ngn_balance, 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <x-icon name="credit-card" class="h-4 w-4" /> USD balance
                </div>
                <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">${{ number_format((float) $wallet->usd_balance, 2) }}</div>
                @if ($usdLocal)
                    <div class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">≈ {{ $usdLocal }} <span class="text-slate-300 dark:text-slate-600">· live rate</span></div>
                @endif
            </div>
        </div>

        <h2 class="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recent transactions</h2>
        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Type</th>
                        <th class="px-4 py-2 font-medium">Amount</th>
                        <th class="px-4 py-2 font-medium">Balance after</th>
                        <th class="px-4 py-2 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                    @forelse ($transactions as $txn)
                        <tr wire:key="txn-{{ $txn->id }}" class="text-slate-700 dark:text-slate-200">
                            <td class="px-4 py-2 capitalize">{{ $txn->type }}</td>
                            <td class="px-4 py-2 font-medium {{ in_array($txn->type, ['debit', 'withdrawal']) ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ in_array($txn->type, ['debit', 'withdrawal']) ? '−' : '+' }}{{ $txn->currency }} {{ number_format((float) $txn->amount, 2) }}
                            </td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ $txn->currency }} {{ number_format((float) $txn->balance_after, 2) }}</td>
                            <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ $txn->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{-- Top up (Module 32 pick — Na3ar-17 collapsible payment card, made
             functional): graceful expand, payment-method radios, quick-cash
             blocks — all wired to the real gateway initialisation. --}}
        <div class="nx-topup rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]"
             x-data="{ open: true }">
            <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                    class="flex w-full items-center justify-between gap-2 p-5">
                <span class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                    <x-icon name="zap" class="h-5 w-5 text-accent" /> Top up
                </span>
                <span class="rounded-lg border border-slate-200 p-1 text-slate-400 transition-transform duration-300 dark:border-[#2D4060]" :class="open && 'rotate-180'">
                    <x-icon name="chevron-right" class="h-4 w-4 rotate-90" />
                </span>
            </button>

            <div x-show="open" x-collapse>
                <div class="px-5 pb-5">
                    @if ($error)
                        <div class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                        </div>
                    @endif

                    <form wire:submit="topUp" class="space-y-4">
                        {{-- Pay-in currency (owner request): USD/NGN credit the
                             wallet directly; your local currency is converted to a
                             USD credit locked at the live rate. --}}
                        @php
                            $__payOptions = ['NGN' => 'Naira (NGN)', 'USD' => 'Dollar (USD)'];
                            if (! in_array($displayCurrency, ['USD', 'NGN'], true)) {
                                $__payOptions[$displayCurrency] = ($currencyOptions[$displayCurrency][1] ?? $displayCurrency).' ('.$displayCurrency.')';
                            }
                        @endphp
                        <div class="grid grid-cols-{{ count($__payOptions) }} gap-2" role="radiogroup" aria-label="Pay in">
                            @foreach ($__payOptions as $cur => $curLabel)
                                <button type="button" wire:key="cur-{{ $cur }}" wire:click="$set('currency', '{{ $cur }}')"
                                        role="radio" aria-checked="{{ $currency === $cur ? 'true' : 'false' }}"
                                        @class([
                                            'rounded-xl border px-3 py-2 text-sm font-semibold transition',
                                            'border-primary bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300' => $currency === $cur,
                                            'border-slate-200 text-slate-500 hover:border-primary/40 dark:border-[#2D4060] dark:text-slate-400' => $currency !== $cur,
                                        ])>{{ $curLabel }}</button>
                            @endforeach
                        </div>
                        @if (! in_array($currency, ['USD', 'NGN'], true) && is_numeric($amount) && $amount > 0)
                            <p class="-mt-2 text-xs text-slate-400 dark:text-slate-500">
                                ≈ ${{ number_format(app(\App\Services\Pricing\CurrencyService::class)->toUsd((float) $amount, $currency), 2) }} credited to your wallet (live rate, locked at checkout).
                            </p>
                        @endif

                        {{-- Quick cash blocks --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Quick amounts</label>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach ($currency === 'NGN' ? [1000, 2000, 5000, 10000] : [5, 10, 20, 50] as $quick)
                                    <button type="button" wire:key="quick-{{ $currency }}-{{ $quick }}" wire:click="$set('amount', {{ $quick }})"
                                            @class([
                                                'rounded-xl border px-1 py-2 text-xs font-bold transition',
                                                'border-accent bg-accent/15 text-accent' => (string) $amount === (string) $quick,
                                                'border-slate-200 text-slate-600 hover:border-accent/60 hover:bg-accent/10 dark:border-[#2D4060] dark:text-slate-300' => (string) $amount !== (string) $quick,
                                            ])>
                                        {{ $currency === 'NGN' ? '₦'.number_format($quick) : '$'.$quick }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount</label>
                            <input type="number" step="0.01" min="1" wire:model="amount" placeholder="{{ $currency === 'NGN' ? '5000' : '20' }}"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('amount') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        {{-- Payment mode radio rows (only Active gateways show) --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-500 dark:text-slate-400">Pay with</label>
                            @if (empty($gateways))
                                <div class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-500 dark:border-[#2D4060] dark:text-slate-400">
                                    Online top-up is being set up. Please check back shortly.
                                </div>
                            @else
                                <div class="space-y-2">
                                    @foreach ($gateways as $gw => [$gwLabel, $gwHint])
                                        <label wire:key="gw-{{ $gw }}"
                                               @class([
                                                   'flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition',
                                                   'border-primary bg-primary/5 shadow-sm dark:bg-primary/15' => $gateway === $gw,
                                                   'border-slate-200 hover:border-primary/40 dark:border-[#2D4060]' => $gateway !== $gw,
                                               ])>
                                            <input type="radio" wire:model.live="gateway" value="{{ $gw }}" class="text-primary focus:ring-primary/40">
                                            <x-payment-icon :slug="$gw" class="h-9" />
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $gwLabel }}</span>
                                                <span class="block truncate text-[11px] text-slate-400 dark:text-slate-500">{{ $gwHint }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('gateway') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <button type="submit" @disabled(empty($gateways)) wire:loading.attr="disabled" wire:target="topUp"
                                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark disabled:opacity-60">
                            <span wire:loading.remove wire:target="topUp" class="inline-flex items-center gap-2"><x-icon name="credit-card" class="h-4 w-4" /> Continue to payment</span>
                            <span wire:loading wire:target="topUp" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Starting…</span>
                        </button>

                        {{-- Accepted methods (real brand logos) — trust strip. --}}
                        <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1">
                            <span class="mr-1 text-[11px] text-slate-400 dark:text-slate-500">We accept</span>
                            @foreach (['visa', 'mastercard', 'googlepay', 'applepay'] as $mark)
                                <x-payment-icon :slug="$mark" class="h-6" />
                            @endforeach
                        </div>
                        <p class="text-center text-[11px] text-slate-400 dark:text-slate-500">You’ll be redirected to a secure payment page.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
