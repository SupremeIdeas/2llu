<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Security — two-factor authentication</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        The admin panel requires TOTP two-factor authentication. Scan the code with an authenticator app
        (Google Authenticator, Authy, 1Password) and confirm to unlock the rest of the panel.
    </p>

    @if (! $confirmed)
        <div class="mb-6 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>Two-factor authentication is required before you can use the admin panel. Finish enrolment below.</span>
        </div>
    @endif

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
        @if ($confirmed)
            <div class="flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-400">
                <x-icon name="badge-check" class="h-5 w-5" /> Two-factor authentication is active.
            </div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Keep your recovery codes somewhere safe — each can be used once if you lose your authenticator.
            </p>

            @if (! empty($recoveryCodes))
                <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-4 font-mono text-xs text-slate-700 dark:bg-[#243352] dark:text-slate-200">
                    @foreach ($recoveryCodes as $rc)
                        <span>{{ $rc }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                    <x-icon name="refresh" class="h-4 w-4" /> Regenerate recovery codes
                </button>
                @if ($canDisable)
                    <button type="button" wire:click="disable" wire:loading.attr="disabled" wire:target="disable"
                            wire:confirm="Turn off two-factor authentication for your admin account?"
                            class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:text-red-400 dark:hover:bg-red-950/40">
                        <x-icon name="x" class="h-4 w-4" /> Disable 2FA
                    </button>
                @endif
            </div>
        @elseif ($enabled)
            {{-- Enabled but not yet confirmed: show the QR + secret + confirm step. --}}
            <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                <div class="shrink-0 rounded-lg bg-white p-3 shadow-sm">{!! $qr !!}</div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Scan this QR code, or enter the setup key manually:
                    </p>
                    <code class="mt-2 block break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:bg-[#243352] dark:text-slate-200">{{ $secret }}</code>

                    @if (! empty($recoveryCodes))
                        <p class="mt-4 text-xs font-medium text-slate-500 dark:text-slate-400">Recovery codes (save these now):</p>
                        <div class="mt-1 grid grid-cols-2 gap-1 rounded-lg bg-slate-50 p-3 font-mono text-[11px] text-slate-700 dark:bg-[#243352] dark:text-slate-200">
                            @foreach ($recoveryCodes as $rc)
                                <span>{{ $rc }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <form wire:submit="confirm" class="mt-6 border-t border-slate-100 pt-5 dark:border-[#243352]">
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Enter the 6-digit code from your app</label>
                <div class="flex items-start gap-2">
                    <input wire:model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                           class="w-40 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm tracking-widest dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirm"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirm">Confirm &amp; activate</span>
                        <span wire:loading wire:target="confirm" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Verifying…</span>
                    </button>
                </div>
                @error('code') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </form>
        @else
            {{-- Not enabled yet. --}}
            <div class="flex items-start gap-3">
                <x-icon name="shield" class="mt-0.5 h-6 w-6 text-primary" />
                <div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Two-factor authentication is not set up on your admin account yet. Turn it on to generate a
                        QR code and recovery codes.
                    </p>
                    <button type="button" wire:click="enable" wire:loading.attr="disabled" wire:target="enable"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="enable" class="inline-flex items-center gap-2"><x-icon name="shield" class="h-4 w-4" /> Enable two-factor</span>
                        <span wire:loading wire:target="enable" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Setting up…</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Site protection (super-admin only). Plain-language toggles for the two
         security headers most likely to clash with a specific host. --}}
    @if ($canManageSite)
        <div class="mt-8">
            <h2 class="mb-1 text-lg font-bold text-slate-900 dark:text-slate-100">Site protection</h2>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                These extra web-security shields are <span class="font-medium">on by default</span> and recommended. Only turn one off if something on your site visibly stops working after installing on your server — then tell your developer.
            </p>

            @if ($siteSaved)
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
                    <x-icon name="badge-check" class="h-4 w-4" /> {{ $siteSaved }}
                </div>
            @endif

            <form wire:submit="saveSiteProtection" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Content protection (CSP)</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Blocks malicious scripts from loading. Turn off only if a trusted embed/widget you added won’t appear.</span>
                    </span>
                    <x-ui.switch wire:model="csp_enabled" label="Content protection (CSP)" class="mt-1" />
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-slate-100 pt-4 dark:border-[#243352]">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Force secure connection (HSTS)</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Tells browsers to always use HTTPS. Turn off only if your server isn’t on HTTPS yet.</span>
                    </span>
                    <x-ui.switch wire:model="hsts_enabled" label="Force secure connection (HSTS)" class="mt-1" />
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-slate-100 pt-4 dark:border-[#243352]">
                    <span>
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Require two-factor for admin login</span>
                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Off by default — sign in with just email &amp; password. Turn on to require an authenticator code as an extra layer for everyone in the panel.</span>
                    </span>
                    <x-ui.switch wire:model="admin_2fa_required" label="Require two-factor for admin login" class="mt-1" />
                </label>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSiteProtection"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveSiteProtection">Save site protection</span>
                        <span wire:loading wire:target="saveSiteProtection" class="inline-flex items-center gap-2"><x-icon name="refresh" class="h-4 w-4 animate-spin" /> Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
