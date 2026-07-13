<x-layouts.app title="Refund Policy — NaaraSim">
    <main class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Home
        </a>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Refund policy</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Plain and honest. Digital connectivity is delivered instantly, so refunds follow clear rules.</p>

        @php
            $sections = [
                ['You are never charged for a failed delivery', 'If a number or eSIM fails to provision, or an OTP never arrives within its window, your wallet is refunded automatically — no ticket needed.'],
                ['eSIM data plans', 'Because an eSIM is issued the moment you buy, an activated eSIM can’t be refunded. If you were charged but the eSIM never issued, it’s auto-refunded. Buy the right size first — use the data estimator and the device-compatibility check on the checkout page.'],
                ['Virtual & verification numbers', 'Disposable OTP numbers are refunded automatically if no code arrives in time. Rentals and permanent numbers are non-refundable once active, but you can cancel future renewals any time.'],
                ['Wallet top-ups', 'Wallet balance is spent on your purchases and isn’t separately cashed out. Unused balance stays in your wallet for future orders.'],
                ['Something looks wrong?', 'Contact support and we’ll make it right. Genuine errors on our side are always refunded.'],
            ];
        @endphp

        <div class="mt-8 space-y-4">
            @foreach ($sections as [$title, $body])
                <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $body }}</p>
                </section>
            @endforeach
        </div>

        @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
            <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl('a refund question') }}" target="_blank" rel="noopener"
               class="mt-6 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="message-circle" class="h-4 w-4" /> Chat with support on WhatsApp
            </a>
        @else
            <p class="mt-6 text-sm text-slate-500 dark:text-slate-400">Need help? Email <a href="mailto:{{ \App\Support\Niche\SupportLinks::email() }}" class="text-primary hover:underline">{{ \App\Support\Niche\SupportLinks::email() }}</a>.</p>
        @endif
    </main>
</x-layouts.app>
