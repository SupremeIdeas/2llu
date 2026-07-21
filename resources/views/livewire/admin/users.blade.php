<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Users</h1>
        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-[#243352]">{{ number_format($totals['all']) }} total</span>
            <span class="rounded-full bg-green-50 px-2.5 py-1 text-green-700 dark:bg-green-950/40 dark:text-green-300">{{ number_format($totals['active']) }} active</span>
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ number_format($totals['deactivated']) }} paused</span>
        </div>
    </div>

    {{-- Search + filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[12rem]">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email…"
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>
        <select wire:model.live="filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-200">
            <option value="all">All users</option>
            <option value="active">Active</option>
            <option value="deactivated">Deactivated</option>
            <option value="staff">Staff & admins</option>
            <option value="merchants">Merchants</option>
        </select>
    </div>

    {{-- Skeleton while a search/filter round-trips (premium loading feel). --}}
    <div wire:loading.flex wire:target="search,filter" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <x-ui.skeleton-rows :count="6" class="w-full" />
    </div>

    <div wire:loading.remove wire:target="search,filter" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400 dark:border-[#243352]">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Joined</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#243352]">
                @forelse ($users as $u)
                    <tr wire:key="user-{{ $u->id }}" class="hover:bg-slate-50 dark:hover:bg-[#243352]/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">{{ \Illuminate\Support\Str::of($u->name)->trim()->substr(0, 2) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900 dark:text-slate-100">{{ $u->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $u->email }}</p>
                                </div>
                                @foreach ($u->getRoleNames()->take(2) as $role)
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-[#243352] dark:text-slate-300">{{ str_replace('_', ' ', $role) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @if ($u->isDeactivated())
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300"><x-icon name="pause" class="h-3 w-3" /> Paused</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300"><x-icon name="badge-check" class="h-3 w-3" /> Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="view({{ $u->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                                    {{ $viewing?->id === $u->id ? 'Hide' : 'View' }}
                                </button>
                                <button type="button" wire:click="toggleActive({{ $u->id }})"
                                        wire:confirm="{{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }} {{ $u->name }}?"
                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold {{ $u->isDeactivated() ? 'bg-primary text-white hover:bg-primary-dark' : 'border border-slate-200 text-slate-600 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-300' }}">
                                    {{ $u->isDeactivated() ? 'Reactivate' : 'Deactivate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    @if ($viewing?->id === $u->id)
                        <tr wire:key="view-{{ $u->id }}" class="bg-slate-50 dark:bg-[#141F33]">
                            <td colspan="4" class="px-4 py-4">
                                <div class="grid gap-4 sm:grid-cols-4">
                                    <div><p class="text-xs text-slate-400">USD wallet</p><p class="font-semibold text-slate-800 dark:text-slate-100">${{ number_format((float) ($viewing->wallet->usd_balance ?? 0), 2) }}</p></div>
                                    <div><p class="text-xs text-slate-400">NGN wallet</p><p class="font-semibold text-slate-800 dark:text-slate-100">₦{{ number_format((float) ($viewing->wallet->ngn_balance ?? 0), 0) }}</p></div>
                                    <div><p class="text-xs text-slate-400">eSIM orders</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->esimOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-slate-400">Number orders</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->smsOrders()->count() }}</p></div>
                                    <div><p class="text-xs text-slate-400">Country</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->country_code ?: '—' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Display currency</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->display_currency ?: 'USD' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Verified</p><p class="font-semibold text-slate-800 dark:text-slate-100">{{ $viewing->email_verified_at ? 'Yes' : 'No' }}</p></div>
                                    <div><p class="text-xs text-slate-400">Roles</p><p class="font-semibold capitalize text-slate-800 dark:text-slate-100">{{ str_replace('_', ' ', $viewing->getRoleNames()->implode(', ')) ?: 'user' }}</p></div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No users match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
