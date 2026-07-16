<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Pages</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Edit your public website with no code. Change any text, upload section images, hide sections, or reorder
        them — changes go live the moment you save. "Reset section" restores the original brand copy.
    </p>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ($pages as $p)
                <button wire:click="$set('page', '{{ $p }}')"
                        class="rounded-full px-4 py-1.5 text-sm font-semibold transition
                        {{ $page === $p ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-[#243352] dark:text-slate-300 dark:hover:bg-[#2D4060]' }}">
                    {{ ucwords(str_replace('-', ' ', $p)) }}
                </button>
            @endforeach
        </div>
        <a href="{{ $page === 'home' ? url('/') : url('/'.$page) }}" target="_blank" rel="noopener"
           class="text-sm font-medium text-primary hover:underline">View page</a>
    </div>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($sections as $key => $fields)
            <details class="group rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]" wire:key="sec-{{ $page }}-{{ $key }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4">
                    <span class="flex items-center gap-3">
                        <x-icon name="chevron-right" class="h-4 w-4 text-slate-400 transition group-open:rotate-90" />
                        <span class="font-semibold capitalize text-slate-800 dark:text-slate-100">{{ str_replace('-', ' ', $key) }}</span>
                        @unless ($fields['visible'] ?? true)
                            <x-ui.tag variant="soon">Hidden</x-ui.tag>
                        @endunless
                    </span>
                    <span class="flex items-center gap-1.5" x-on:click.prevent.stop>
                        <button type="button" wire:click="moveSection('{{ $key }}', -1)" title="Move up"
                                class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 -rotate-90" />
                        </button>
                        <button type="button" wire:click="moveSection('{{ $key }}', 1)" title="Move down"
                                class="rounded-lg border border-slate-200 p-1.5 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:hover:bg-[#243352]">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-90" />
                        </button>
                        <label class="ml-2 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <x-ui.switch wire:model="sections.{{ $key }}.visible" label="Show section" /> Show
                        </label>
                    </span>
                </summary>

                <div class="space-y-3 border-t border-slate-100 p-4 dark:border-[#243352]">
                    @foreach ($fields as $field => $value)
                        @continue(in_array($field, ['visible', 'order', 'image'], true))
                        <div wire:key="f-{{ $page }}-{{ $key }}-{{ $field }}">
                            <label class="mb-1 block text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ str_replace('_', ' ', $field) }}</label>
                            @if (mb_strlen((string) $value) > 90)
                                <textarea wire:model="sections.{{ $key }}.{{ $field }}" rows="3"
                                          class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                            @else
                                <input type="text" wire:model="sections.{{ $key }}.{{ $field }}"
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                            @endif
                        </div>
                    @endforeach

                    {{-- Section image --}}
                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 dark:border-[#243352]">
                        @if (! empty($fields['image']))
                            <img src="{{ $fields['image'] }}" class="h-14 w-24 rounded-lg border border-slate-200 object-cover dark:border-[#2D4060]">
                            <button type="button" wire:click="removeImage('{{ $key }}')" class="text-xs font-medium text-red-600 hover:underline">Remove image</button>
                        @else
                            <span class="text-xs text-slate-400">No section image</span>
                        @endif
                        @if ($imageSection === $key)
                            <input type="file" wire:model="imageUpload" accept="image/png,image/jpeg,image/webp"
                                   class="text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:text-slate-400">
                            <button type="button" wire:click="uploadImage('{{ $key }}')" wire:loading.attr="disabled"
                                    class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark disabled:opacity-60">Use image</button>
                            @error('imageUpload') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @else
                            <button type="button" wire:click="$set('imageSection', '{{ $key }}')" class="text-xs font-medium text-primary hover:underline">
                                {{ empty($fields['image']) ? 'Add image' : 'Replace image' }}
                            </button>
                        @endif
                        <button type="button" wire:click="resetSection('{{ $key }}')" wire:confirm="Restore this section to the original brand copy?"
                                class="ml-auto text-xs font-medium text-slate-400 hover:text-red-600 hover:underline">Reset section</button>
                    </div>
                </div>
            </details>
        @endforeach
    </div>

    <div class="sticky bottom-4 mt-6 flex justify-end">
        <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="badge-check">Save page</x-ui.btn>
    </div>
</div>
