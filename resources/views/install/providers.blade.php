<x-layouts.app title="Install — Providers">
    <x-install-shell :step="4">
        <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-slate-100">Provider keys</h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            All optional. Any left blank shows <span class="font-semibold">Coming Soon</span> until you add the key later —
            launch one product at a time.
        </p>
        <form method="POST" action="/install/finalize" class="space-y-4">
            @csrf
            @php
                $fields = [
                    'ESIMGO_API_KEY' => 'eSIM Go API key',
                    'AIRALO_CLIENT_ID' => 'Airalo client ID',
                    'AIRALO_CLIENT_SECRET' => 'Airalo client secret',
                    'GETATEXT_API_KEY' => 'Getatext API key (US numbers)',
                    'FIVESIM_API_KEY' => '5sim API key (global numbers)',
                    'PAYSTACK_SECRET_KEY' => 'Paystack secret key',
                    'FLUTTERWAVE_SECRET_KEY' => 'Flutterwave secret key',
                    'STRIPE_SECRET_KEY' => 'Stripe secret key',
                ];
            @endphp
            <div class="grid grid-cols-1 gap-3">
                @foreach ($fields as $env => $label)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</label>
                        <input name="key_{{ $env }}" placeholder="Leave blank for Coming Soon"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between">
                <a href="/install/application" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:border-[#2D4060] dark:text-slate-300">Back</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-action px-4 py-2.5 font-semibold text-white hover:opacity-90">
                    <x-icon name="zap" class="h-4 w-4" /> Finish install
                </button>
            </div>
        </form>
    </x-install-shell>
</x-layouts.app>
