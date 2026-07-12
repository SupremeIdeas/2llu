<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-slate-100">Wallet</h1>

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
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                <x-icon name="zap" class="h-5 w-5 text-accent" /> Top up
            </h2>

            @if ($error)
                <div class="mt-3 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                    <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                </div>
            @endif

            <form wire:submit="topUp" class="mt-4 space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount</label>
                    <input type="number" step="0.01" min="1" wire:model="amount"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('amount') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select wire:model="currency" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="NGN">NGN</option>
                        <option value="USD">USD</option>
                    </select>
                    <select wire:model="gateway" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="paystack">Paystack</option>
                        <option value="flutterwave">Flutterwave</option>
                        <option value="stripe">Stripe</option>
                    </select>
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="topUp"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="topUp" class="inline-flex items-center gap-2"><x-icon name="credit-card" class="h-4 w-4" /> Continue to payment</span>
                    <span wire:loading wire:target="topUp" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Starting…</span>
                </button>
            </form>
        </div>
    </div>
</div>
