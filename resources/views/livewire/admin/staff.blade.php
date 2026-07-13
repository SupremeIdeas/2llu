<div class="mx-auto max-w-4xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Staff &amp; roles</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Create staff members and grant granular scopes. Staff can act only within the scopes you give them, and can never delete a user account.
    </p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Promote an existing active user (blueprint Section 27) --}}
    <form wire:submit="promote" class="mb-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="badge-check" class="h-4 w-4 text-primary" /> Make an existing user staff
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Pick any active customer by email. They keep their account and end-user access, and simply gain the scopes you choose.</p>

        @if ($promoteError)
            <div class="rounded-lg bg-red-50 p-2 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $promoteError }}</div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">User email</label>
            <input wire:model="promoteEmail" type="email" placeholder="customer@example.com" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
            @error('promoteEmail') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Scopes</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($grantable as $scope)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-[#2D4060] dark:text-slate-200">
                        <input type="checkbox" wire:model="promoteScopes" value="{{ $scope }}" class="rounded text-primary">
                        <span>{{ $labels[$scope] ?? $scope }} <span class="text-xs text-slate-400">({{ $scope }})</span></span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="promote"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="promote">Make staff</span>
                <span wire:loading wire:target="promote" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Promoting…</span>
            </button>
        </div>
    </form>

    {{-- Create a brand-new staff account --}}
    <form wire:submit="createStaff" class="mb-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <x-icon name="id-card" class="h-4 w-4 text-primary" /> Or create a brand-new staff account
        </h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                <input wire:model="name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('name') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Email</label>
                <input wire:model="email" type="email" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('email') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Temporary password</label>
                <input wire:model="password" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('password') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Scopes</label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($grantable as $scope)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-[#2D4060] dark:text-slate-200">
                        <input type="checkbox" wire:model="scopes" value="{{ $scope }}" class="rounded text-primary">
                        <span>{{ $labels[$scope] ?? $scope }} <span class="text-xs text-slate-400">({{ $scope }})</span></span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="createStaff"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="createStaff">Create staff member</span>
                <span wire:loading wire:target="createStaff" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Creating…</span>
            </button>
        </div>
    </form>

    {{-- Existing staff --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
        @forelse ($staff as $member)
            <div wire:key="staff-{{ $member->id }}" class="border-b border-slate-100 px-5 py-4 last:border-0 dark:border-[#243352]">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ $member->name }} <span class="text-slate-400">·</span> <span class="text-slate-500 dark:text-slate-400">{{ $member->email }}</span></p>
                    </div>
                    <button type="button" wire:click="revoke({{ $member->id }})" wire:confirm="Revoke staff access for {{ $member->email }}? Their account stays but loses all staff scopes."
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                        <x-icon name="x" class="h-3.5 w-3.5" /> Revoke
                    </button>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($grantable as $scope)
                        @php $has = $member->hasPermissionTo($scope); @endphp
                        <button type="button" wire:click="toggleScope({{ $member->id }}, '{{ $scope }}')"
                                @class([
                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                    'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-primary' => $has,
                                    'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-400' => ! $has,
                                ])>
                            <x-icon :name="$has ? 'check' : 'x'" class="h-3 w-3" /> {{ $labels[$scope] ?? $scope }}
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                <x-icon name="id-card" class="mx-auto mb-2 h-6 w-6" /> No staff members yet.
            </div>
        @endforelse
    </div>
</div>
