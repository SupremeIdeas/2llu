<div class="mx-auto max-w-3xl">
    <div class="mb-6 flex items-center gap-3">
        @if ($merchant->logo_url)
            <img src="{{ $merchant->logo_url }}" alt="{{ $merchant->business_name }}" class="h-12 w-12 rounded-xl object-contain ring-1 ring-black/5 dark:ring-white/10">
        @else
            <span class="flex h-12 w-12 items-center justify-center rounded-xl text-lg font-bold uppercase text-white" style="background-color: {{ $merchant->brand_color ?: '#0A6E6E' }};">{{ \Illuminate\Support\Str::of($merchant->business_name)->trim()->substr(0, 2) }}</span>
        @endif
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $merchant->business_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Your NaaraSim reseller storefront.</p>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs text-slate-500 dark:text-slate-400">Customers</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $customerCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs text-slate-500 dark:text-slate-400">Available</p>
            <p class="mt-1 text-2xl font-bold text-primary dark:text-teal-300">${{ number_format($balance, 2) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <p class="text-xs text-slate-500 dark:text-slate-400">Lifetime earned</p>
            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">${{ number_format($lifetime, 2) }}</p>
        </div>
    </div>

    {{-- Merchant V2 --}}
    @if ($merchant->isV2())
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('merchant.clients') }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-primary/40 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="users" class="h-5 w-5" /></span>
                <div><p class="font-semibold text-slate-900 dark:text-white">Clients</p><p class="text-xs text-slate-400">Manage eSIMs for people without an account</p></div>
            </a>
            <a href="{{ route('developer') }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-primary/40 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20 dark:text-teal-300"><x-icon name="key" class="h-5 w-5" /></span>
                <div><p class="font-semibold text-slate-900 dark:text-white">Developer portal</p><p class="text-xs text-slate-400">API keys &amp; docs — first-class access</p></div>
            </a>
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/[0.08] to-accent/[0.06] p-5 dark:border-primary/30 dark:from-primary/15 dark:to-accent/10">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-bold text-slate-900 dark:text-white"><span class="nx-badge">V2</span> Upgrade to Merchant V2</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Manage eSIMs &amp; numbers for clients who never log in, plus first-class developer-portal access. One-time <span class="font-semibold">${{ number_format(\App\Support\MerchantSettings::upgradePriceUsd(), 2) }}</span> from your wallet.</p>
                </div>
                <button type="button" wire:click="upgradeToV2" wire:loading.attr="disabled" wire:target="upgradeToV2"
                        class="shrink-0 rounded-2xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-60">
                    <span wire:loading.remove wire:target="upgradeToV2">Upgrade now</span>
                    <span wire:loading wire:target="upgradeToV2">Upgrading…</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Invite link --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]"
         x-data="{ copied: false, copy() { navigator.clipboard.writeText('{{ $inviteUrl }}').then(() => { this.copied = true; setTimeout(() => this.copied = false, 1500); }); } }">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"><x-icon name="link" class="h-4 w-4" /> Your invite link</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Share this — anyone who signs up through it becomes your customer, and you earn on every purchase they make.</p>
        <div class="flex items-center gap-2">
            <input type="text" readonly value="{{ $inviteUrl }}"
                   class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-200">
            <button type="button" x-on:click="copy()"
                    class="flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="copy" class="h-4 w-4" /> <span x-text="copied ? 'Copied' : 'Copy'"></span>
            </button>
        </div>
    </div>

    {{-- Storefront branding --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"><x-icon name="image" class="h-4 w-4" /> Storefront</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Business name</label>
                <input type="text" wire:model="businessName" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('businessName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Brand colour</label>
                <div class="flex items-center gap-2">
                    <input type="color" wire:model="brandColor" class="h-9 w-12 shrink-0 rounded border border-slate-300 dark:border-[#2D4060]">
                    <input type="text" wire:model="brandColor" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                @error('brandColor') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Logo <span class="font-normal text-slate-400">(optional, PNG/JPG)</span></label>
            <input type="file" wire:model="logo" accept="image/*"
                   class="block text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-300">
            @error('logo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <button type="button" wire:click="saveStorefront" wire:loading.attr="disabled" wire:target="saveStorefront,logo"
                class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="check" class="h-4 w-4" /> Save storefront
        </button>
        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Pricing is set by NaaraSim — you brand the storefront, we run the engine.</p>
    </div>

    {{-- Withdraw earnings --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"><x-icon name="wallet" class="h-4 w-4" /> Withdraw earnings</h2>
        @if (! $payoutsEnabled)
            <p class="text-sm text-slate-500 dark:text-slate-400">Withdrawals aren’t open yet — your earnings keep accruing safely.</p>
        @elseif ($accounts->isEmpty())
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Add a verified payout account on the <a href="{{ route('rewards.withdraw') }}" wire:navigate class="font-medium text-primary hover:underline">withdrawals page</a> first.
            </p>
        @else
            @if ($withdrawError)
                <div class="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $withdrawError }}</div>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">To account</label>
                    <select wire:model="accountId" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($accounts as $acct)
                            <option value="{{ $acct->id }}">{{ $acct->bank_name ?? $acct->bank_code }} · {{ $acct->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Amount (USD)</label>
                    <input type="number" step="0.01" min="0" wire:model="amountUsd" placeholder="0.00"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
            </div>
            <button type="button" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw"
                    class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="send" class="h-4 w-4" /> Request withdrawal
            </button>
        @endif
    </div>

    {{-- Earnings ledger --}}
    @if ($ledger->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Recent earnings</h2>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-[#243352] dark:border-[#2D4060] dark:bg-[#1A2840]">
                @foreach ($ledger as $row)
                    <div class="flex items-center justify-between px-4 py-3 text-sm" wire:key="earn-{{ $row->id }}">
                        <div>
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ ucfirst($row->type) }}<span class="text-slate-400"> · {{ $row->description }}</span></p>
                            <p class="text-xs text-slate-400">{{ $row->created_at->diffForHumans() }}</p>
                        </div>
                        <span @class([
                            'font-semibold',
                            'text-green-600 dark:text-green-400' => (float) $row->amount > 0,
                            'text-slate-500 dark:text-slate-400' => (float) $row->amount < 0,
                        ])>{{ (float) $row->amount > 0 ? '+' : '' }}${{ number_format((float) $row->amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Customers --}}
    @if ($customers->isNotEmpty())
        <div class="mt-6">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Your customers</h2>
            <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-[#243352] dark:border-[#2D4060] dark:bg-[#1A2840]">
                @foreach ($customers as $customer)
                    <div class="flex items-center gap-3 px-4 py-3 text-sm" wire:key="cust-{{ $customer->id }}">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">{{ \Illuminate\Support\Str::of($customer->name)->trim()->substr(0, 1) }}</span>
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ $customer->name }}</span>
                        <span class="ml-auto text-xs text-slate-400">Joined {{ $customer->created_at->format('M Y') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
