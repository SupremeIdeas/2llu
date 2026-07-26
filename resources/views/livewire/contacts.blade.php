<div class="mx-auto max-w-lg">
    <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-slate-100">Contacts</h1>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        Save the people you call often. Tap a name to drop it straight into the dialer.
    </p>

    <a href="{{ route('numbers.dialer') }}" wire:navigate
       class="mb-6 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline dark:text-teal-300">
        <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back to the dialer
    </a>

    {{-- Add / edit form --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ $editingId ? 'Edit contact' : 'Add a contact' }}
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                <input type="text" wire:model="name" placeholder="Ada Obi"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Number</label>
                <input type="tel" wire:model="phone" placeholder="+2348012345678"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                @error('phone') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="check" class="h-4 w-4" /> {{ $editingId ? 'Save changes' : 'Add contact' }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancelEdit"
                        class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                    Cancel
                </button>
            @endif
        </div>
    </div>

    {{-- Bulk import --}}
    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-5 dark:border-[#2D4060] dark:bg-[#1A2840]">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <x-icon name="upload" class="h-4 w-4" /> Import a batch
        </h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
            Upload a <strong>.csv</strong> (name, number) or a <strong>.vcf</strong> (vCard) export from your phone or email.
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <input type="file" wire:model="upload" accept=".csv,.txt,.vcf"
                   class="block w-full max-w-xs text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-300 dark:file:bg-primary/20 dark:file:text-teal-300">
            <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import,upload"
                    @disabled(! $upload)
                    class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                <span wire:loading.remove wire:target="import" class="inline-flex items-center gap-2"><x-icon name="upload" class="h-4 w-4" /> Import</span>
                <span wire:loading wire:target="import" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Importing…</span>
            </button>
        </div>
        @error('upload') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        @if ($notice) <p class="mt-2 text-xs text-green-600 dark:text-green-400">{{ $notice }}</p> @endif

        {{-- Progressive enhancement: Android-Chrome OS contact picker. Hidden
             everywhere it isn't supported (desktop, iOS Safari), so it's only ever
             a shortcut — never the primary path. --}}
        <div x-data="{
                supported: (typeof navigator !== 'undefined' && 'contacts' in navigator && 'ContactsManager' in window),
                async pick() {
                    try {
                        const sel = await navigator.contacts.select(['name', 'tel'], { multiple: true });
                        const rows = sel.map(c => ({ name: (c.name && c.name[0]) || '', phone: (c.tel && c.tel[0]) || '' }))
                                        .filter(r => r.phone);
                        if (rows.length) { $wire.importPicked(rows); }
                    } catch (e) { /* user cancelled or unsupported */ }
                }
             }"
             x-show="supported" x-cloak class="mt-3">
            <button type="button" x-on:click="pick()"
                    class="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-200 dark:hover:bg-[#243352]">
                <x-icon name="inbox" class="h-4 w-4" /> Import from phone
            </button>
        </div>
    </div>

    {{-- Contact list --}}
    <div class="mt-6">
        <div class="relative mb-3">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search contacts…"
                   class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
        </div>

        {{-- Skeleton while a search round-trips (premium loading feel). --}}
        <div wire:loading.flex wire:target="search" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            <x-ui.skeleton-rows :count="4" class="w-full" />
        </div>

        <div wire:loading.remove wire:target="search">
        @if ($contacts->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 dark:border-[#2D4060] dark:bg-[#1A2840] dark:text-slate-400">
                No contacts yet. Add one above or import a batch.
            </div>
        @else
            <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-[#243352] dark:border-[#2D4060] dark:bg-[#1A2840]">
                @foreach ($contacts as $contact)
                    <div class="flex items-center gap-3 px-4 py-3" wire:key="contact-{{ $contact->id }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold uppercase text-primary dark:bg-primary/20 dark:text-teal-300">
                            {{ \Illuminate\Support\Str::of($contact->name)->trim()->substr(0, 2) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $contact->name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $contact->phone_number }}</p>
                        </div>
                        @if (\App\Support\ProviderStatus::isActive('twilio'))
                            <a href="{{ route('numbers.dialer', ['to' => $contact->phone_number]) }}" wire:navigate
                               aria-label="Call {{ $contact->name }}"
                               class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-white transition hover:bg-primary-dark">
                                <x-icon name="phone" class="h-4 w-4" />
                            </a>
                        @endif
                        <button type="button" wire:click="edit({{ $contact->id }})" aria-label="Edit {{ $contact->name }}"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]">
                            <x-icon name="settings" class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="delete({{ $contact->id }})" wire:confirm="Remove {{ $contact->name }} from your contacts?"
                                aria-label="Delete {{ $contact->name }}"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:border-red-300 hover:text-red-600 dark:border-[#2D4060] dark:text-slate-400">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
        </div>{{-- /wire:loading.remove search --}}
    </div>
</div>
