<div class="mx-auto max-w-5xl">
    <h1 class="mb-1 flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
        <x-icon name="list" class="h-6 w-6 text-butter" /> Circle Priority Rules
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">The exact weighting used for turn order stays proprietary — this is where you set the purpose order and tie-breaker that <span class="font-brand-mono">priority_auto</span> plans apply automatically. Turn 1 is always first-come-first-served regardless of this rule.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        {{-- Editor --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $editingId ? 'Edit rule' : 'New rule' }}</h2>
                <button type="button" wire:click="newRule" class="text-xs font-medium text-butter hover:underline">+ New rule</button>
            </div>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                    <input wire:model="name" type="text" placeholder="Default priority order"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('name') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-500 dark:text-slate-400">Purpose order (highest priority first)</label>
                    <ol class="space-y-1.5">
                        @foreach ($orderedPurposes as $i => $purpose)
                            <li class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352]">
                                <span class="font-brand-mono w-5 tabular-nums text-slate-400">{{ $i + 1 }}</span>
                                <span class="flex-1 text-slate-800 dark:text-slate-200">{{ $purposeLabels[$purpose] ?? $purpose }}</span>
                                <button type="button" wire:click="moveUp({{ $i }})" @disabled($i === 0)
                                        class="rounded p-1 text-slate-500 hover:bg-slate-200 disabled:opacity-30 dark:text-slate-400 dark:hover:bg-[#2D4060]" aria-label="Move up">
                                    <x-icon name="chevron-right" class="h-4 w-4 -rotate-90" />
                                </button>
                                <button type="button" wire:click="moveDown({{ $i }})" @disabled($i === count($orderedPurposes) - 1)
                                        class="rounded p-1 text-slate-500 hover:bg-slate-200 disabled:opacity-30 dark:text-slate-400 dark:hover:bg-[#2D4060]" aria-label="Move down">
                                    <x-icon name="chevron-right" class="h-4 w-4 rotate-90" />
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Tie-breaker</label>
                        <select wire:model="tieBreaker" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="join_order">Join order</option>
                            <option value="random">Random</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700 dark:text-slate-300">
                        <input wire:model="isActive" type="checkbox" class="rounded border-slate-300 text-butter focus:ring-butter">
                        Active (used by plans with no rule of their own)
                    </label>
                </div>

                <button type="submit" class="rounded-lg bg-butter px-4 py-2 text-sm font-semibold text-espresso hover:brightness-95">
                    Save rule
                </button>
            </form>
        </section>

        {{-- List --}}
        <section class="space-y-3">
            @foreach ($rules as $rule)
                <button type="button" wire:click="edit('{{ $rule->id }}')"
                        class="w-full rounded-2xl border p-4 text-left transition
                        {{ $editingId === $rule->id ? 'border-butter bg-butter/10' : 'border-slate-200 bg-white hover:bg-slate-50 dark:border-[#2D4060] dark:bg-[#1A2840] dark:hover:bg-[#243352]' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $rule->name }}</p>
                        @if ($rule->is_active)
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-primary-dark dark:bg-primary/20 dark:text-primary">Active</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ collect($rule->ordered_purposes)->map(fn ($p) => $purposeLabels[$p] ?? $p)->join(' → ') }}
                    </p>
                </button>
            @endforeach
        </section>
    </div>
</div>
