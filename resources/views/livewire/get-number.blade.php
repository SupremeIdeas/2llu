<div class="mx-auto max-w-lg">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Get a Number</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Receive verification codes for any country. We pick the best network for you.</p>

    {{-- Live Voice (Twilio) — only when voice is Active (rides the same keys). --}}
    @if (\App\Support\ProviderStatus::isActive('twilio'))
        {{-- Part B: in-browser international dialer. --}}
        <a href="{{ route('numbers.dialer') }}" wire:navigate
           class="mb-3 flex items-center gap-2 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5 text-sm font-medium text-primary transition hover:bg-primary/10 dark:border-primary/30 dark:bg-primary/10 dark:text-teal-300">
            <x-icon name="phone" class="h-4 w-4" /> Call any international number from your browser
            <x-icon name="chevron-right" class="ml-auto h-4 w-4" />
        </a>
        {{-- Part A: call forwarding on a permanent number. --}}
        <a href="{{ route('numbers.forwarding') }}" wire:navigate
           class="mb-6 flex items-center gap-2 rounded-xl border border-primary/20 bg-primary/5 px-4 py-2.5 text-sm font-medium text-primary transition hover:bg-primary/10 dark:border-primary/30 dark:bg-primary/10 dark:text-teal-300">
            <x-icon name="phone" class="h-4 w-4" /> Forward calls on your permanent number to your phone
            <x-icon name="chevron-right" class="ml-auto h-4 w-4" />
        </a>
    @endif

    @if ($order)
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]"
             @if ($order->status === 'waiting') wire:poll.3s @endif>
            @if ($couponNote)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                    <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $couponNote }}
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-sm text-slate-500 dark:text-slate-400">Your number</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary-dark dark:bg-primary/20 dark:text-primary">
                    <x-service-icon :slug="$order->service_name" class="h-4 w-4" /> {{ ucfirst($order->service_name) }}
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
                    <div class="flex items-center gap-2">
                        <x-country-flag :country="$country" class="h-5 w-7 shrink-0" wire:key="flag-{{ $country }}" />
                        <select wire:model.live="country" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @foreach ($countries as $slug => $label)
                                <option value="{{ $slug }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div x-data="{ q: '' }">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Service <span class="text-slate-400">· {{ count($services) }} available</span></label>
                    {{-- Full service catalogue (searchable). Each option shows its
                         mark — admin-uploaded logo, provider artwork, or glyph. --}}
                    <div class="relative mb-2">
                        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="text" x-model="q" placeholder="Search {{ count($services) }} services…"
                               class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div class="grid max-h-64 grid-cols-3 gap-2 overflow-y-auto pr-1" role="radiogroup" aria-label="Service">
                        @foreach ($services as $svc => $label)
                            <button type="button" wire:key="svc-{{ $svc }}" wire:click="$set('service', '{{ $svc }}')"
                                    x-show="q === '' || '{{ Str::lower($label) }}'.includes(q.toLowerCase())"
                                    role="radio" aria-checked="{{ $service === $svc ? 'true' : 'false' }}"
                                    @class([
                                        'flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-center text-[11px] font-medium leading-tight transition',
                                        'border-primary bg-primary/5 text-primary shadow-sm dark:bg-primary/15 dark:text-teal-300' => $service === $svc,
                                        'border-slate-200 text-slate-600 hover:border-primary/40 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]' => $service !== $svc,
                                    ])>
                                <x-service-icon :slug="$svc" class="h-7 w-7" />
                                <span class="line-clamp-2">{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Type</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5 dark:border-[#2D4060] dark:text-slate-200 dark:has-[:checked]:bg-primary/10">
                            <input type="radio" wire:model.live="type" value="otp" class="text-primary"> One-time code
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5 dark:border-[#2D4060] dark:text-slate-200 dark:has-[:checked]:bg-primary/10">
                            <input type="radio" wire:model.live="type" value="rental" class="text-primary"> Rental
                        </label>
                    </div>
                    @if ($type === 'rental')
                        <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                            A rental keeps your number for the subscription period — it receives
                            <strong>unlimited</strong> SMS
                            @if ($fullRentAvailable)
                                (pick <em>Any service</em> below to receive codes from <strong>every</strong> service, or one service to save).
                            @else
                                for the service you pick.
                            @endif
                        </p>
                        @if ($fullRentAvailable)
                            <button type="button" wire:click="$set('service', '{{ \App\Services\SMS\NumberRequest::SERVICE_ANY }}')"
                                    @class([
                                        'mt-2 flex w-full items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-primary bg-primary/5 text-primary dark:bg-primary/15 dark:text-teal-300' => $service === \App\Services\SMS\NumberRequest::SERVICE_ANY,
                                        'border-slate-200 text-slate-700 hover:border-primary/40 dark:border-[#2D4060] dark:text-slate-200' => $service !== \App\Services\SMS\NumberRequest::SERVICE_ANY,
                                    ])>
                                <x-icon name="globe" class="h-4 w-4" />
                                <span>Any service <span class="text-xs font-normal text-slate-400">· receive all SMS</span></span>
                            </button>
                        @endif
                    @endif
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Coupon code <span class="font-normal text-slate-400">(optional)</span></label>
                    <input wire:model="coupon" type="text" placeholder="e.g. WELCOME10" autocomplete="off"
                           class="w-full rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-sm uppercase tracking-wider text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Applied to the live price when you order.</p>
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
