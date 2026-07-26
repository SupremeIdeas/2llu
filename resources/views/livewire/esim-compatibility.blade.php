<div>
    @if ($open)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" x-data
             @keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" wire:click="close"></div>

            <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl dark:bg-[#0D1B2A]">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                        <x-icon name="signal" class="h-5 w-5" gradient /> Check device compatibility
                    </h2>
                    <button wire:click="close" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
                </div>

                {{-- EID / *#06# banner --}}
                <div class="mx-5 mt-4 rounded-xl border border-primary/25 bg-primary/5 p-3 dark:border-primary/30 dark:bg-primary/10">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">The definitive check</p>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">Dial <span class="rounded bg-white px-1.5 py-0.5 font-mono font-bold text-primary dark:bg-[#1B2A44] dark:text-teal-300">*#06#</span> on your device — if an <strong>EID</strong> number appears, your device supports eSIM.</p>
                </div>

                {{-- Search --}}
                <div class="px-5 pt-4">
                    <div class="relative">
                        <x-icon name="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search your device…"
                               class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                </div>

                {{-- OS pill tabs --}}
                <div class="flex gap-2 px-5 pt-3">
                    @foreach (['apple' => 'Apple', 'android' => 'Android', 'others' => 'Others'] as $key => $label)
                        <button wire:click="setOs('{{ $key }}')"
                                class="flex-1 rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $os === $key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300 dark:hover:bg-[#2D4060]' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                {{-- Device accordions --}}
                <div class="mt-3 flex-1 overflow-y-auto px-5 pb-5" x-data="{ openCat: null }" wire:key="cats-{{ $os }}">
                    @forelse ($grouped as $category => $devices)
                        <div class="mt-2 overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]" x-data>
                            <button type="button" @click="openCat = (openCat === '{{ $category }}' ? null : '{{ $category }}')"
                                    class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-slate-800 dark:text-slate-100">
                                <span>{{ $categoryLabels[$category] ?? ucfirst($category) }} <span class="ml-1 text-xs font-normal text-slate-400">{{ $devices->count() }}</span></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition" ::class="openCat === '{{ $category }}' ? 'rotate-90' : ''" />
                            </button>
                            <div x-show="openCat === '{{ $category }}'" x-collapse>
                                <ul class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/5 dark:border-white/5">
                                    @foreach ($devices as $d)
                                        <li class="flex items-center gap-2 px-4 py-2 text-sm text-slate-600 dark:text-slate-300">
                                            <x-icon name="badge-check" class="h-3.5 w-3.5 shrink-0 text-green-500" /> {{ $d->device_name }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-sm text-slate-400">
                            No matching device. Try the <span class="font-mono">*#06#</span> check above, or another spelling.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
