<div class="mx-auto max-w-6xl">
    <h1 class="mb-1 flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-slate-100">
        <x-icon name="layers" class="h-6 w-6 text-butter" /> Circle Plans
    </h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Every contribution tier a 2LLUber can join. Plans start as drafts — flip a plan to active once its numbers are confirmed.</p>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        {{-- List, grouped by cycle type --}}
        <section class="space-y-6">
            <div class="flex justify-end">
                <button type="button" wire:click="newPlan" class="rounded-lg bg-butter px-4 py-2 text-sm font-semibold text-espresso hover:brightness-95">
                    + New plan
                </button>
            </div>

            @foreach ($plans as $cycleType => $group)
                <div>
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ ucfirst($cycleType) }}</h2>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-[#2D4060]">
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-slate-100 dark:divide-[#2D4060]">
                                @foreach ($group as $plan)
                                    <tr class="bg-white hover:bg-slate-50 dark:bg-[#1A2840] dark:hover:bg-[#243352] {{ $editingId === $plan->id ? 'ring-1 ring-inset ring-butter' : '' }}">
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ $plan->name }}</p>
                                            <p class="font-brand-mono text-xs tabular-nums text-slate-400 dark:text-slate-500">{{ $plan->currency }} {{ number_format($plan->contribution_amount) }} · {{ $plan->members_per_group }} members</p>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase
                                                {{ $plan->status === 'active' ? 'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-primary' : 'bg-slate-100 text-slate-500 dark:bg-[#243352] dark:text-slate-400' }}">
                                                {{ $plan->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button type="button" wire:click="edit('{{ $plan->id }}')" class="mr-2 text-xs font-medium text-butter hover:underline">Edit</button>
                                            <button type="button" wire:click="toggleStatus('{{ $plan->id }}')" class="mr-2 text-xs font-medium text-slate-500 hover:underline dark:text-slate-400">
                                                {{ $plan->status === 'active' ? 'Set draft' : 'Set active' }}
                                            </button>
                                            <button type="button" wire:click="delete('{{ $plan->id }}')" wire:confirm="Delete this plan? Only possible if no groups exist against it."
                                                    class="text-xs font-medium text-red-500 hover:underline">Delete</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- Editor --}}
        <section class="h-fit rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <h2 class="mb-4 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $editingId ? 'Edit plan' : 'New plan' }}</h2>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                    <input wire:model="name" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('name') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Slug</label>
                    <input wire:model="slug" type="text" placeholder="auto from name if blank" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('slug') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Cycle type</label>
                        <select wire:model="cycleType" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Biweekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Cycle duration</label>
                        <input wire:model="cycleDuration" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Members per group</label>
                        <input wire:model="membersPerGroup" type="number" min="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Currency</label>
                        <input wire:model="currency" type="text" maxlength="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Contribution amount</label>
                        <input wire:model="contributionAmount" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-brand-mono text-sm tabular-nums text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Registration fee</label>
                        <input wire:model="registrationFee" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-brand-mono text-sm tabular-nums text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Platform fee %</label>
                        <input wire:model="platformFeePercent" type="number" step="0.01" min="0" max="100" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-brand-mono text-sm tabular-nums text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Min income multiplier</label>
                        <input wire:model="minIncomeMultiplier" type="number" step="0.01" min="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-right font-brand-mono text-sm tabular-nums text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">KYC level required</label>
                        <select wire:model="kycLevelRequired" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="basic">Basic</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700 dark:text-slate-300">
                        <input wire:model="requiresBankStatement" type="checkbox" class="rounded border-slate-300 text-butter focus:ring-butter">
                        Requires bank statement
                    </label>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Countries allowed (comma-separated ISO-2)</label>
                    <input wire:model="countriesAllowed" type="text" placeholder="NG, GH" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Turn-sort strategy</label>
                        <select wire:model.live="turnSortStrategy" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="fifo">FIFO</option>
                            <option value="priority_auto">Priority (auto)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Priority rule</label>
                        <select wire:model="priorityRuleId" @disabled($turnSortStrategy !== 'priority_auto')
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 disabled:opacity-40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="">Use the active rule</option>
                            @foreach ($priorityRules as $rule)
                                <option value="{{ $rule->id }}">{{ $rule->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Tooltip details</label>
                    <textarea wire:model="tooltipDetails" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Bullet points (one per line)</label>
                    <textarea wire:model="bulletPoints" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Display order</label>
                        <input wire:model="displayOrder" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                <button type="submit" class="w-full rounded-lg bg-butter px-4 py-2 text-sm font-semibold text-espresso hover:brightness-95">
                    Save plan
                </button>
            </form>
        </section>
    </div>
</div>
