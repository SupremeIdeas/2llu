<div>
    <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Pricing</h1>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Global settings --}}
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                    <x-icon name="settings" class="h-5 w-5 text-primary" /> Global
                </h2>
                <form wire:submit="saveGlobal" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Default markup %</label>
                        <input type="number" step="0.1" wire:model="default_markup_pct"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('default_markup_pct') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Minimum profit floor (USD)</label>
                        <input type="number" step="0.01" wire:model="minimum_profit_usd"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        @error('minimum_profit_usd') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveGlobal"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveGlobal">Save &amp; reprice all</span>
                        <span wire:loading wire:target="saveGlobal" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Saving…</span>
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-100 pt-4 dark:border-[#243352]">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Provider keys</h3>
                    <ul class="space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                        <li class="flex items-center">eSIM Go API key <x-admin-help-icon provider="esimgo" field="api_key" /></li>
                        <li class="flex items-center">Airalo client secret <x-admin-help-icon provider="airalo" field="client_secret" /></li>
                        <li class="flex items-center">Getatext API key <x-admin-help-icon provider="getatext" field="api_key" /></li>
                        <li class="flex items-center">5sim API key <x-admin-help-icon provider="fivesim" field="api_key" /></li>
                        <li class="flex items-center">Paystack secret key <x-admin-help-icon provider="paystack" field="secret_key" /></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Plans + live profit --}}
        <div class="lg:col-span-2">
            @if ($summary && $editingPlanId)
                <div class="mb-4 rounded-xl border border-primary/30 bg-primary/5 p-5 dark:border-primary/40 dark:bg-primary/10">
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-slate-100">
                        <x-icon name="zap" class="h-5 w-5 text-accent" /> Live profit
                    </h2>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div><div class="text-xs text-slate-500 dark:text-slate-400">Cost</div><div class="font-bold text-slate-900 dark:text-slate-100">${{ number_format($summary['cost_price'], 2) }}</div></div>
                        <div><div class="text-xs text-slate-500 dark:text-slate-400">Retail</div><div class="font-bold text-slate-900 dark:text-slate-100">${{ number_format($summary['retail_price'], 2) }}</div></div>
                        <div><div class="text-xs text-slate-500 dark:text-slate-400">Profit</div><div class="font-bold text-green-600 dark:text-green-400">${{ number_format($summary['profit_usd'], 2) }}</div></div>
                        <div><div class="text-xs text-slate-500 dark:text-slate-400">Margin</div><div class="font-bold text-green-600 dark:text-green-400">{{ number_format($summary['profit_pct'], 1) }}%</div></div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Override markup %</label>
                            <input type="number" step="0.1" wire:model.live.debounce.300ms="override_markup_pct" placeholder="use global"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Fixed retail (USD)</label>
                            <input type="number" step="0.01" wire:model.live.debounce.300ms="manual_retail_usd" placeholder="use formula"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" wire:model="is_active" class="rounded text-primary"> Active</label>
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" wire:model="is_featured" class="rounded text-primary"> Featured</label>
                        <div class="ml-auto flex gap-2">
                            <button type="button" wire:click="cancelEdit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-[#2D4060] dark:text-slate-200">Cancel</button>
                            <button type="button" wire:click="savePlan" class="rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-dark">Save plan</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Plan</th>
                            <th class="px-4 py-2 font-medium">Retail</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                        @foreach ($plans as $plan)
                            <tr wire:key="pp-{{ $plan->id }}" class="text-slate-700 dark:text-slate-200">
                                <td class="px-4 py-2">{{ $plan->name }}</td>
                                <td class="px-4 py-2 font-medium">${{ number_format((float) $plan->final_retail_usd, 2) }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $plan->is_active,
                                        'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ! $plan->is_active,
                                    ])>{{ $plan->is_active ? 'Active' : 'Off' }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" wire:click="editPlan({{ $plan->id }})" class="text-primary hover:underline">Edit</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $plans->links() }}</div>
        </div>
    </div>
</div>
