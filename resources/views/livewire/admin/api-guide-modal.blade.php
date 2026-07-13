<div>
    @if ($open && $content)
        <div x-data
             x-on:keydown.escape.window="$wire.close()"
             class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-navy/50 dark:bg-black/70" wire:click="close"></div>

            {{-- Panel: full-screen under 768px, centered 720px on desktop --}}
            <div class="relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-t-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-[#2D4060] dark:bg-[#1A2840] sm:w-[720px] sm:rounded-2xl">
                <div class="flex items-start justify-between">
                    <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-slate-100">
                        <x-icon name="help-circle" class="h-5 w-5 text-primary" /> {{ $title }}
                    </h2>
                    <button type="button" wire:click="close" aria-label="Close"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-[#243352] dark:hover:text-slate-200">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Config key</dt>
                        <dd class="mt-1 rounded-lg bg-slate-100 px-3 py-2 font-mono text-slate-800 dark:bg-[#243352] dark:text-slate-200">{{ $content['config_key'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Where to get it</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $content['where'] }}</dd>
                    </div>
                    <div class="flex gap-8">
                        <div>
                            <dt class="font-semibold text-slate-500 dark:text-slate-400">Format</dt>
                            <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $content['format'] }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-500 dark:text-slate-400">Required</dt>
                            <dd class="mt-1">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $content['required'],
                                    'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ! $content['required'],
                                ])>
                                    {{ $content['required'] ? 'Required' : 'Optional' }}
                                </span>
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>
    @endif
</div>
