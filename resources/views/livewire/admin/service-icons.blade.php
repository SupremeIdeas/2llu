<div class="mx-auto max-w-3xl">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Service icons</h1>
    <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
        Every service a customer can buy a number for shows a logo automatically — a built-in mark, or the
        provider's own artwork once your APIs are live. Upload an official logo here to override any of them,
        or add a brand-new service (PNG/WebP, square, up to 1 MB).
    </p>

    @if ($saved)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950/40 dark:text-green-300">
            <x-icon name="badge-check" class="h-4 w-4" /> {{ $saved }}
        </div>
    @endif

    {{-- Upload / add --}}
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <div class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Service</label>
                <select wire:model="uploadSlug" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm capitalize dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    <option value="">Add a new one…</option>
                    @foreach ($slugs as $slug)
                        <option value="{{ $slug }}">{{ $slug }}</option>
                    @endforeach
                </select>
                @if ($uploadSlug === '')
                    <input type="text" wire:model="newSlug" placeholder="e.g. bumble"
                           class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('newSlug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @endif
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Logo file</label>
                <input type="file" wire:model="upload" accept="image/png,image/webp,image/jpeg,image/svg+xml"
                       class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white dark:text-slate-400">
                @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-ui.btn variant="primary" type="button" wire:click="save" target="save" icon="upload">Save logo</x-ui.btn>
        </div>
    </div>

    {{-- Current set --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
        @foreach ($slugs as $slug)
            <div wire:key="svc-{{ $slug }}" class="nx-card !p-4 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center text-primary dark:text-teal-300">
                    <x-service-icon :slug="$slug" class="h-10 w-10" />
                </span>
                <p class="mt-2 text-xs font-semibold capitalize text-slate-700 dark:text-slate-200">{{ $slug }}</p>
                @if (isset($overrides[$slug]))
                    <button type="button" wire:click="remove('{{ $slug }}')" class="mt-1 text-[11px] font-medium text-red-500 hover:underline">Remove override</button>
                @else
                    <p class="mt-1 text-[11px] text-slate-400">Built-in mark</p>
                @endif
            </div>
        @endforeach
    </div>
</div>
