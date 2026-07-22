<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Support agent</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Configure NaaraCare — your AI customer-support agent. It sees each customer's own orders, device
        and balance to solve their specific problem, and escalates to a human when needed.
    </p>

    @if (! $configured)
        <div class="mb-6 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>The agent is off until you add your <b>Anthropic API key</b> on the <a href="{{ route('admin.api-keys') }}" class="font-semibold underline">API keys</a> page.</span>
        </div>
    @endif

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Agent name</label>
                    <input type="text" wire:model="agent_name" placeholder="Nia"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <p class="mt-1 text-[11px] text-slate-400">A friendly human first name customers will chat with.</p>
                    @error('agent_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Persona &amp; tone</label>
                    <textarea wire:model="persona" rows="3"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    <p class="mt-1 text-[11px] text-slate-400">How the agent should sound — warm, concise, human. (Safety rules are always enforced regardless.)</p>
                    @error('persona') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Platform knowledge (optional)</label>
                    <textarea wire:model="knowledge" rows="8" placeholder="Paste FAQs, policies, setup tips, coverage notes… the agent will ground answers on this."
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    <p class="mt-1 text-[11px] text-slate-400">Authoritative facts the agent should prefer. Never put secrets or internal costs here.</p>
                    @error('knowledge') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Autopilot: how much the AI may resolve on its own. --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1B2A44]">
            <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Autopilot resolution</h2>
            <p class="mb-4 text-[11px] text-slate-400">Let {{ $agent_name ?: 'the agent' }} resolve safe tickets itself — re-fetch a stuck code, resend eSIM setup, and mark tickets resolved. Refunds, account changes, pricing and anything sensitive are always handed to staff.</p>

            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="autopilot_enabled"
                       class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-[#2D4060] dark:bg-[#243352]">
                <span class="text-sm text-slate-700 dark:text-slate-200">Allow the AI to resolve safe tickets on autopilot</span>
            </label>

            <div class="mt-4 max-w-xs">
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Goodwill ceiling (USD per ticket)</label>
                <input type="number" step="0.01" min="0" max="20" wire:model="goodwill_cap_usd"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                <p class="mt-1 text-[11px] text-slate-400">The most goodwill (in NaaraCredits value) the AI may grant for a genuine minor inconvenience. <strong>0 turns goodwill off</strong> — the AI escalates instead. Credits can never push a sale below cost.</p>
                @error('goodwill_cap_usd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="badge-check" class="h-4 w-4" /> Save
            </button>
        </div>
    </form>
</div>
