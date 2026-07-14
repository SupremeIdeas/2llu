<div class="mx-auto flex h-[calc(100vh-9rem)] max-w-2xl flex-col">
    <div class="mb-3 flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
            <x-icon name="message-circle" class="h-5 w-5" />
        </span>
        <div>
            <h1 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $agentName }} — Support</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ask about eSIMs, numbers, your orders or device compatibility.</p>
        </div>
    </div>

    {{-- Transcript --}}
    <div class="flex-1 space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 dark:border-[#2D4060] dark:bg-[#1A2840]"
         x-data x-init="$el.scrollTop = $el.scrollHeight"
         x-on:message-added.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($messages as $m)
            @if ($m['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm text-white">{{ $m['body'] }}</div>
                </div>
            @else
                <div class="flex flex-col items-start gap-1">
                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-2 text-sm text-slate-800 dark:bg-[#243352] dark:text-slate-100">{{ $m['body'] }}</div>
                    @if (! empty($m['nav']))
                        <a href="{{ $m['nav'] }}" wire:navigate
                           class="ml-1 inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary hover:bg-primary/10">
                            <x-icon name="chevron-right" class="h-3 w-3" /> Take me there
                        </a>
                    @endif
                </div>
            @endif
        @empty
            <div class="flex h-full items-center justify-center text-center text-sm text-slate-400">
                <p>Hi, I'm {{ $agentName }}. How can I help you today?</p>
            </div>
        @endforelse

        <div wire:loading wire:target="send" class="flex items-center gap-2 text-sm text-slate-400">
            <span class="flex gap-1">
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300 [animation-delay:-0.3s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300 [animation-delay:-0.15s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300"></span>
            </span>
            {{ $agentName }} is typing…
        </div>
    </div>

    {{-- Composer --}}
    <form wire:submit="send" class="mt-3 flex items-center gap-2"
          x-data x-on:message-added.window="$el.querySelector('input').focus()">
        <input type="text" wire:model="draft" autocomplete="off" placeholder="Type your message…"
               class="flex-1 rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"
               wire:loading.attr="disabled" wire:target="send">
        @error('draft') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        <button type="submit" wire:loading.attr="disabled" wire:target="send"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark disabled:opacity-60">
            <x-icon name="send" class="h-5 w-5" />
        </button>
    </form>
</div>
