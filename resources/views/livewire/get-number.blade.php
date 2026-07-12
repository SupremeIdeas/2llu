<div class="mx-auto max-w-lg">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Get a Number</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Receive verification codes for any country. We pick the best network for you.</p>

    @if ($order)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]"
             @if ($order->status === 'waiting') wire:poll.3s @endif>
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500 dark:text-slate-400">Your number</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                    <x-icon name="hash" class="h-3.5 w-3.5" /> {{ ucfirst($order->service_name) }}
                </span>
            </div>
            <div class="mt-1 flex items-center gap-2 text-xl font-bold text-slate-900 dark:text-slate-100">
                <x-icon name="phone" class="h-5 w-5 text-primary" /> {{ $order->phone_number }}
            </div>

            <div class="mt-5 rounded-xl bg-slate-50 p-5 text-center dark:bg-[#243352]">
                @if ($order->status === 'completed' && $order->otp_code)
                    <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Your code</div>
                    <div class="mt-1 flex items-center justify-center gap-2 text-3xl font-bold tracking-widest text-primary dark:text-primary">
                        <x-icon name="badge-check" class="h-6 w-6 text-green-500" /> {{ $order->otp_code }}
                    </div>
                @elseif ($order->status === 'timeout')
                    <div class="flex items-center justify-center gap-2 text-sm text-warning">
                        <x-icon name="refresh" class="h-5 w-5" /> No code arrived in time — your wallet was refunded.
                    </div>
                @else
                    <div class="flex items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <x-icon name="refresh" class="h-5 w-5 animate-spin text-primary" /> Waiting for your code…
                    </div>
                @endif
            </div>

            <button type="button" wire:click="reset_"
                    class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                Get another number
            </button>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
            @if ($error)
                <div class="mb-4 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                    <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                </div>
            @endif

            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Country</label>
                    <select wire:model="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($countries as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Service</label>
                    <select wire:model="service" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm capitalize text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @foreach ($services as $svc)
                            <option value="{{ $svc }}">{{ ucfirst($svc) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Type</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5 dark:border-[#2D4060] dark:text-slate-200 dark:has-[:checked]:bg-primary/10">
                            <input type="radio" wire:model="type" value="otp" class="text-primary"> One-time code
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5 dark:border-[#2D4060] dark:text-slate-200 dark:has-[:checked]:bg-primary/10">
                            <input type="radio" wire:model="type" value="rental" class="text-primary"> Rental
                        </label>
                    </div>
                </div>
            </div>

            <button type="button" wire:click="order" wire:loading.attr="disabled" wire:target="order"
                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 font-semibold text-white transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/50 disabled:opacity-60">
                <span wire:loading.remove wire:target="order" class="inline-flex items-center gap-2"><x-icon name="phone" class="h-5 w-5" /> Get number</span>
                <span wire:loading wire:target="order" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-5 w-5 animate-spin" /> Reserving…</span>
            </button>
            <p class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">Price is charged from your wallet. Auto-refund if no code arrives.</p>
        </div>
    @endif
</div>
