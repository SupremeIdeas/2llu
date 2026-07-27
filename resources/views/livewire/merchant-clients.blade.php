<div class="mx-auto max-w-2xl" x-data="{ sheet: false, assign: false }"
     @close-client-sheet.window="sheet = false">
    {{-- Header --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Clients</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage eSIMs for people who don't have an account. Wallet balance <span class="font-semibold text-slate-700 dark:text-slate-200">${{ number_format($walletUsd, 2) }}</span></p>
        </div>
        <button type="button" @click="$wire.newClient(); sheet = true" class="flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 transition hover:bg-primary-dark">
            <x-icon name="plus" class="h-4 w-4" /> Add client
        </button>
    </div>

    {{-- Search --}}
    <div class="relative mb-5">
        <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, device or contact…"
               class="w-full rounded-full border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
    </div>

    {{-- Client list --}}
    <div class="space-y-2.5">
        @forelse ($clients as $client)
            <div wire:key="mc-{{ $client->id }}" class="rounded-2xl border border-slate-200 nx-glass-tile p-4 dark:border-white/10 {{ $client->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ $client->name }}</p>
                        <p class="text-xs text-slate-400">
                            @if ($client->device){{ $client->device }} · @endif{{ $client->esim_orders_count }} eSIM{{ $client->esim_orders_count === 1 ? '' : 's' }}@if ($client->contact) · {{ $client->contact }}@endif
                        </p>
                    </div>
                    @unless ($client->is_active)<span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-white/10 dark:text-slate-300">Inactive</span>@endunless
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="$wire.set('assignClientId', {{ $client->id }}); assign = true" @disabled(! $client->is_active)
                            class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/20 disabled:opacity-50 dark:bg-primary/20 dark:text-teal-300"><x-icon name="plus" class="mr-0.5 inline h-3.5 w-3.5" /> Assign eSIM</button>
                    <button type="button" wire:click="edit({{ $client->id }})" @click="sheet = true" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-300">Edit</button>
                    <button type="button" wire:click="toggleActive({{ $client->id }})" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-white/10 dark:text-slate-400">{{ $client->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-400 dark:border-white/10">No clients yet. Add your first client to start managing eSIMs for them.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $clients->links() }}</div>

    {{-- Add / edit client sheet --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="sheet = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="sheet = false"></div>
        <div x-show="sheet" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            <h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">{{ $editingId ? 'Edit client' : 'New client' }}</h2>
            <div class="space-y-3">
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label><input type="text" wire:model="name" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">@error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Device</label><input type="text" wire:model="device" placeholder="iPhone 15" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Contact</label><input type="text" wire:model="contact" placeholder="phone / email" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></div>
                </div>
                <div><label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Notes</label><textarea wire:model="notes" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></textarea></div>
                @if ($error)<p class="text-xs text-red-600">{{ $error }}</p>@endif
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="w-full rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">{{ $editingId ? 'Save changes' : 'Add client' }}</button>
            </div>
        </div>
    </div>

    {{-- Assign eSIM sheet --}}
    <div x-show="assign" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" @keydown.escape.window="assign = false" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="assign = false"></div>
        <div x-show="assign" x-transition class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>
            <h2 class="mb-1 text-base font-bold text-slate-900 dark:text-white">Assign an eSIM</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Charged from your merchant wallet at your reseller price.</p>
            <select wire:model="assignPlanId" class="mb-3 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                <option value="">Choose a plan…</option>
                @foreach ($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
            </select>
            @if ($error)<p class="mb-2 text-xs text-red-600">{{ $error }}</p>@endif
            <button type="button" wire:click="assign" @click="if (! $wire.error) assign = false" wire:loading.attr="disabled" wire:target="assign" @disabled(! $assignPlanId)
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                <span wire:loading.remove wire:target="assign"><x-icon name="check" class="mr-1 inline h-4 w-4" /> Buy &amp; assign</span>
                <span wire:loading wire:target="assign" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Provisioning…</span>
            </button>
        </div>
    </div>
</div>
