<div>
    <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Overview</h1>

    @unless ($privileged)
        {{-- Staff view: scopes only, no business figures. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                <x-icon name="shield-check" class="h-4 w-4 text-primary" /> Your access
            </h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">You can work within these scopes. Ask a super admin if you need more.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($myScopes as $scope)
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary-dark dark:bg-primary/20 dark:text-primary">
                        <x-icon name="check" class="h-3 w-3" /> {{ $scopeLabels[$scope] ?? $scope }}
                    </span>
                @empty
                    <span class="text-sm text-slate-400 dark:text-slate-500">No scopes assigned yet.</span>
                @endforelse
            </div>
        </div>
    @else

    {{-- 30-day profit (admin-only figures) --}}
    <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @php
            $tiles = [
                ['Revenue (30d)', '$'.number_format($revenue, 2), 'credit-card', 'text-slate-900 dark:text-slate-100'],
                ['Cost (30d)', '$'.number_format($cost, 2), 'package', 'text-slate-900 dark:text-slate-100'],
                ['Gross profit', '$'.number_format($profit, 2), 'zap', 'text-green-600 dark:text-green-400'],
                ['Gross margin', number_format($margin, 1).'%', 'signal', 'text-green-600 dark:text-green-400'],
            ];
        @endphp
        @foreach ($tiles as [$label, $value, $icon, $valueClass])
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
                </div>
                <div class="mt-2 text-2xl font-bold {{ $valueClass }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Provider wallet health --}}
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="wallet" class="h-4 w-4" /> Provider wallets
            </h2>
            <div class="space-y-2">
                @forelse ($health as $provider => $info)
                    <div wire:key="health-{{ $provider }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <span class="font-medium capitalize text-slate-900 dark:text-slate-100">{{ $provider }}</span>
                        <div class="flex items-center gap-3">
                            @if (! is_null($info['balance'] ?? null))
                                <span class="text-sm text-slate-500 dark:text-slate-400">${{ number_format((float) $info['balance'], 2) }}</span>
                            @endif
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => ($info['status'] ?? '') === 'ok',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => ($info['status'] ?? '') === 'low',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => ($info['status'] ?? '') === 'error',
                                'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ($info['status'] ?? '') === 'coming_soon',
                            ])>{{ ucfirst(str_replace('_', ' ', $info['status'] ?? 'unknown')) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400 dark:border-[#2D4060] dark:text-slate-500">
                        No health data yet. Run <code class="font-mono">providers:health-check</code>.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Active / Coming Soon per product --}}
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <x-icon name="badge-check" class="h-4 w-4" /> Products
            </h2>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($statuses as $provider => $label)
                    <div wire:key="status-{{ $provider }}" class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
                        <span class="capitalize text-slate-700 dark:text-slate-200">{{ $provider }}</span>
                        <x-ui.tag :variant="$label === 'Active' ? 'live' : 'soon'">{{ $label }}</x-ui.tag>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    @endunless
</div>
