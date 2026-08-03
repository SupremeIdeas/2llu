<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Withdraw earnings</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Cash out the NaaraCredits you earned from referrals to your bank account.
    </p>

    {{-- Balance --}}
    <div class="mt-5 rounded-2xl border border-primary/20 bg-primary/5 p-5 dark:border-primary/30 dark:bg-primary/10">
        <p class="text-xs font-semibold uppercase tracking-widest text-primary/70 dark:text-teal-300/70">Available to withdraw</p>
        <p class="mt-1 text-3xl font-bold text-slate-900 dark:text-white">${{ number_format($availableUsd, 2) }}</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($withdrawableCredits, 0) }} withdrawable credits · minimum ${{ number_format($minWithdrawal, 2) }}</p>
    </div>

    @unless ($enabled)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            Withdrawals are currently paused. Please check back soon.
        </div>
    @endunless

    {{-- Payout accounts --}}
    <h2 class="mt-8 text-sm font-semibold text-slate-900 dark:text-slate-100">Your payout accounts</h2>
    <div class="mt-3 space-y-2">
        @forelse ($accounts as $acct)
            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3.5 dark:border-[#2D4060] dark:bg-[#1A2840]" wire:key="acct-{{ $acct->id }}">
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ $acct->account_name }}
                        @if ($acct->is_default) <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary dark:bg-primary/20 dark:text-teal-300">Default</span> @endif
                        {{-- §8: highlight the highest-inbound-volume rail (never hides others). --}}
                        @if ($recommendedGateway && $acct->provider === $recommendedGateway)
                            <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700 dark:bg-green-500/15 dark:text-green-300"><x-icon name="zap" class="h-3 w-3" /> Recommended — fast payout</span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $acct->bank_name }} · {{ $acct->masked_number }} · {{ $acct->currency }}</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @unless ($acct->is_default)
                        <button type="button" wire:click="setDefault({{ $acct->id }})" class="font-medium text-primary hover:underline">Make default</button>
                    @endunless
                    <button type="button" wire:click="removeAccount({{ $acct->id }})" wire:confirm="Remove this account?" class="font-medium text-slate-400 hover:text-red-600">Remove</button>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-400">No payout accounts yet — add one below.</p>
        @endforelse
    </div>

    {{-- Add account --}}
    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Add a bank account</p>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">We confirm the account name with your bank before saving.</p>

        @if ($accountError)
            <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $accountError }}</div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Country</label>
                <select wire:model.live="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="NG">Nigeria</option>
                    <option value="GH">Ghana</option>
                    <option value="KE">Kenya</option>
                    <option value="ZA">South Africa</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Bank</label>
                <select wire:model="bankCode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">Select a bank</option>
                    @foreach ($banks as $bank)
                        <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
                    @endforeach
                </select>
                @error('bankCode') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                @if (empty($banks))
                    <p class="mt-1 text-[11px] text-slate-400">Bank list loads once a payout provider is configured for this country.</p>
                @endif
            </div>
        </div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Account number</label>
            <input type="text" wire:model="accountNumber" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('accountNumber') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <button type="button" wire:click="addAccount" wire:loading.attr="disabled" wire:target="addAccount"
                class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="check" wire:loading.remove wire:target="addAccount" class="h-4 w-4" />
            <x-ui.spinner wire:loading wire:target="addAccount" class="h-4 w-4" />
            Verify &amp; add account
        </button>
    </div>

    {{-- Withdraw --}}
    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Request a withdrawal</p>

        @if ($withdrawError)
            <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $withdrawError }}</div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">To account</label>
                <select wire:model="accountId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">Select an account</option>
                    @foreach ($accounts as $acct)
                        <option value="{{ $acct->id }}">{{ $acct->account_name }} · {{ $acct->masked_number }}</option>
                    @endforeach
                </select>
                @error('accountId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount (USD)</label>
                <input type="number" step="0.01" min="0" wire:model="amountUsd" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('amountUsd') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
        <button type="button" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw"
                @disabled(! $enabled)
                class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="credit-card" wire:loading.remove wire:target="withdraw" class="h-4 w-4" />
            <x-ui.spinner wire:loading wire:target="withdraw" class="h-4 w-4" />
            Withdraw to bank
        </button>
    </div>
</div>
