<x-layouts.app title="Legal & Refund Policy — NaaraSim">
    <main class="mx-auto max-w-3xl px-4 py-12">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
            <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Home
        </a>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Legal &amp; Refund Policy</h1>

        <section class="mt-8 space-y-3 text-slate-600 dark:text-slate-300">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                <x-icon name="shield-check" class="h-5 w-5 text-primary" /> Refunds
            </h2>
            <p>eSIM bundles that have not been started are revocable and refundable to your NaaraSim wallet. Verification numbers auto-refund if no code arrives within the delivery window. Wallet balances are store credit, spendable across eSIMs and numbers.</p>
        </section>

        <section class="mt-8 space-y-3 text-slate-600 dark:text-slate-300">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                <x-icon name="file-text" class="h-5 w-5 text-primary" /> Fair use &amp; honesty
            </h2>
            <p>Verification numbers receive SMS (and 5sim can receive a voice OTP); they are not full phone lines. Only permanent numbers make and receive calls. We sell each product as exactly what it is.</p>
        </section>
    </main>
</x-layouts.app>
