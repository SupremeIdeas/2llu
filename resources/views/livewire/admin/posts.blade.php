<div class="mx-auto max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Blog</h1>
        @unless ($showForm)
            <button type="button" wire:click="newPost" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark">
                <x-icon name="check" class="h-4 w-4" /> New post
            </button>
        @endunless
    </div>

    @if ($saved)
        <div class="mb-6 flex items-center gap-2 rounded-lg bg-primary/10 p-3 text-sm text-primary-dark dark:bg-primary/20 dark:text-primary">
            <x-icon name="badge-check" class="h-4 w-4 shrink-0" /> {{ $saved }}
        </div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $editingId ? 'Edit post' : 'New post' }}</h2>
                <button type="button" wire:click="resetForm" class="text-xs text-slate-400 underline hover:text-slate-600 dark:hover:text-slate-300">Cancel</button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Title</label>
                    <input wire:model.blur="title" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('title') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Slug <span class="font-normal text-slate-400">(URL)</span></label>
                    <input wire:model="slug" type="text" placeholder="auto from title" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    @error('slug') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Category</label>
                    <input wire:model="category" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Status</label>
                    <select wire:model="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                        <option value="draft">Draft (hidden)</option>
                        <option value="published">Published (public)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Cover image <span class="font-normal text-slate-400">(WebP/JPEG, ≤ 2 MB)</span></label>
                <input wire:model="cover" type="file" accept="image/webp,image/jpeg,image/png"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-primary dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-300">
                <div wire:loading wire:target="cover" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                @error('cover') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                @if ($coverUrl)
                    <img src="{{ $coverUrl }}" alt="" class="mt-2 h-24 w-40 rounded-lg border border-slate-200 object-cover dark:border-[#2D4060]">
                @endif
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Excerpt <span class="font-normal text-slate-400">(short summary)</span></label>
                <textarea wire:model="excerpt" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('excerpt') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Body</label>
                <p class="mb-1 text-[11px] text-slate-400 dark:text-slate-500">Use <code class="font-mono">## Heading</code>, <code class="font-mono">- bullet</code>, and blank lines between paragraphs. HTML is rendered as plain text for safety.</p>
                <textarea wire:model="body" rows="14" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm leading-relaxed text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                @error('body') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>

            <details class="rounded-lg border border-slate-200 p-3 dark:border-[#2D4060]">
                <summary class="cursor-pointer text-xs font-semibold text-slate-500 dark:text-slate-400">SEO (optional)</summary>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Meta title <span class="font-normal text-slate-400">(defaults to title)</span></label>
                        <input wire:model="meta_title" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Meta description <span class="font-normal text-slate-400">(defaults to excerpt)</span></label>
                        <textarea wire:model="meta_description" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"></textarea>
                    </div>
                </div>
            </details>

            <button type="submit" wire:loading.attr="disabled" wire:target="save,cover"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-dark disabled:opacity-60">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-icon name="check" class="h-4 w-4" /> Save post</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Saving…</span>
            </button>
        </form>
    @else
        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-[#2D4060]">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 dark:bg-[#243352] dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Title</th>
                        <th class="px-4 py-2 font-medium">Category</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white dark:divide-[#243352] dark:bg-[#1A2840]">
                    @forelse ($posts as $post)
                        <tr wire:key="post-{{ $post->id }}" class="text-slate-700 dark:text-slate-200">
                            <td class="px-4 py-2.5 font-medium">{{ $post->title }}</td>
                            <td class="px-4 py-2.5">{{ $post->category }}</td>
                            <td class="px-4 py-2.5">
                                <x-ui.tag :variant="$post->status === 'published' ? 'live' : 'soon'">{{ ucfirst($post->status) }}</x-ui.tag>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ optional($post->published_at ?? $post->created_at)->format('M j, Y') }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" wire:click="edit({{ $post->id }})" class="text-xs font-medium text-primary underline hover:text-primary-dark">Edit</button>
                                <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="Delete this post permanently?" class="ml-2 text-xs font-medium text-red-500 underline hover:text-red-700 dark:text-red-400">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400 dark:text-slate-500">No posts yet. Click “New post” to write your first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</div>
