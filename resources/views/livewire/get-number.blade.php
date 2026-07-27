<div class="mx-auto max-w-3xl">
    {{-- Premium 4-image interchanging-reveal hero (Numbers V6 §0). --}}
    @include('partials.numbers-hero')

    {{-- Six-card bento grid (Numbers V6 §1). Verify/Rent/Line cards open the
         dedicated product modals below; the rest route to their pages. --}}
    @include('partials.numbers-bento')

    {{-- Active order surfaced on the page when no modal is open, so an
         in-progress number/OTP stays visible after the modal is closed. The
         order is auth-scoped in the component (IDOR-safe). --}}
    @if ($order && $modal === '')
        <div class="mt-6 rounded-2xl border border-slate-200 nx-glass-tile p-5 shadow-sm dark:border-[#2D4060]"
             @if ($order->status === 'waiting') wire:poll.3s @endif>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-slate-400">Your number</p>
                    <p class="select-all font-mono text-lg font-bold text-slate-900 dark:text-white">{{ $order->phone_number }}</p>
                </div>
                <div class="text-right">
                    @if ($order->otp_code)
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">Code</p>
                        <p class="select-all font-mono text-2xl font-bold text-primary dark:text-teal-300">{{ $order->otp_code }}</p>
                    @else
                        <p class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"><x-ui.spinner class="h-4 w-4" /> Waiting for the code…</p>
                    @endif
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" wire:click="reset_" wire:loading.attr="disabled" wire:target="reset_" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/5">Done</button>
                <a href="{{ route('dashboard') }}" wire:navigate class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">View on dashboard</a>
            </div>
        </div>
    @endif

    {{-- Product modals (pop like the mobile sheet) + the shared pickers. --}}
    @include('partials.numbers-modals')
    <livewire:country-picker />
    <livewire:service-picker />


</div>
