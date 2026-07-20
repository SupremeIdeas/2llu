<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Become a merchant</h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        Run your own co-branded storefront on NaaraSim — resell eSIMs and numbers to
        your customers and earn on every sale, settled straight to your bank.
    </p>

    @unless ($programmeOpen)
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
            The merchant programme isn't open yet. Please check back soon.
        </div>
    @else
        {{-- Stepper --}}
        <ol class="mt-6 flex items-center gap-2 text-xs font-semibold">
            <li class="flex items-center gap-2 {{ $kybVerified ? 'text-primary' : 'text-slate-900 dark:text-slate-100' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $kybVerified ? 'bg-primary text-white' : 'bg-slate-200 text-slate-600 dark:bg-[#243352] dark:text-slate-300' }}">1</span> Verify business
            </li>
            <span class="h-px w-8 bg-slate-200 dark:bg-[#2D4060]"></span>
            <li class="flex items-center gap-2 {{ $merchant ? 'text-primary' : 'text-slate-400' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $merchant ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500 dark:bg-[#243352]' }}">2</span> Apply
            </li>
        </ol>

        @if ($merchant && $merchant->status === 'active')
            <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/40">
                <p class="font-semibold text-green-800 dark:text-green-300">You're a merchant</p>
                <p class="mt-1 text-sm text-green-700/80 dark:text-green-400/80">Your storefront <strong>{{ $merchant->business_name }}</strong> is live. Manage it from your Merchant area.</p>
            </div>
        @elseif ($merchant && $merchant->status === 'pending')
            <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900/50 dark:bg-amber-950/40">
                <p class="font-semibold text-amber-800 dark:text-amber-300">Application under review</p>
                <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-400/80">We're reviewing <strong>{{ $merchant->business_name }}</strong>. You'll be notified once it's approved.</p>
            </div>
        @elseif (! $kybVerified)
            {{-- Stage 1: KYB --}}
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Verify your business</p>
                @if ($kybAttempt && $kybAttempt->status === 'pending')
                    <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">Your business details are being verified — this usually takes a little while.</div>
                @else
                    @if ($kybAttempt && $kybAttempt->status === 'rejected')
                        <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">We couldn't verify your business{{ $kybAttempt->reason ? ': '.$kybAttempt->reason : '.' }} Please try again.</div>
                    @endif
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Country</label>
                            <select wire:model="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                <option value="NG">Nigeria</option>
                                <option value="GH">Ghana</option>
                                <option value="KE">Kenya</option>
                                <option value="ZA">South Africa</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reg. type</label>
                            <select wire:model="regType" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                                <option value="CAC">CAC / RC</option>
                                <option value="TIN">TIN</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Reg. number</label>
                            <input type="text" wire:model="regNumber" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @error('regNumber') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <button type="button" wire:click="submitKyb" wire:loading.attr="disabled" wire:target="submitKyb"
                            class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                        <x-icon name="shield" class="h-4 w-4" /> Submit for verification
                    </button>
                @endif
            </div>
        @else
            {{-- Stage 2: application --}}
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Set up your storefront</p>
                @if ($error)
                    <div class="mt-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $error }}</div>
                @endif
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Business name</label>
                        <input type="text" wire:model="businessName" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('businessName') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Brand colour</label>
                        <input type="color" wire:model="brandColor" class="h-10 w-full rounded-lg border border-slate-300 bg-white dark:border-[#2D4060] dark:bg-[#243352]">
                    </div>
                </div>
                <button type="button" wire:click="apply" wire:loading.attr="disabled" wire:target="apply"
                        class="mt-4 flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                    <x-icon name="check" class="h-4 w-4" /> Submit application
                </button>
            </div>
        @endif
    @endunless
</div>
