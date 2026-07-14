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
                    <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm text-white">
                        {{ $m['body'] }}
                        @if ($m['voice'])<audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>@endif
                    </div>
                </div>
            @else
                <div class="flex flex-col items-start gap-1">
                    @if ($m['role'] === 'staff')
                        <span class="ml-1 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-primary"><x-icon name="id-card" class="h-3 w-3" /> Human agent</span>
                    @endif
                    <div class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm px-4 py-2 text-sm {{ $m['role'] === 'staff' ? 'bg-primary/10 text-slate-800 dark:bg-primary/20 dark:text-slate-100' : 'bg-slate-100 text-slate-800 dark:bg-[#243352] dark:text-slate-100' }}">
                        {{ $m['body'] }}
                        @if ($m['voice'])
                            <audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>
                        @elseif ($m['voice_pending'])
                            <span class="mt-1 block text-[11px] text-slate-400">Preparing voice reply…</span>
                        @endif
                    </div>
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

    @if ($humanHandling)
        <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <x-icon name="id-card" class="h-4 w-4" /> A member of our team is looking after this conversation. Replies may take a little longer.
        </div>
    @endif

    {{-- Composer --}}
    <div class="mt-3 space-y-2">
        <form wire:submit="send" class="flex items-center gap-2"
              x-data x-on:message-added.window="$el.querySelector('input').focus()">
            <input type="text" wire:model="draft" autocomplete="off" placeholder="Type your message…"
                   class="flex-1 rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/40 dark:border-[#2D4060] dark:bg-[#243352] dark:text-slate-100"
                   wire:loading.attr="disabled" wire:target="send,sendVoice">

            {{-- Voice note: attach an audio clip; auto-sends on select. --}}
            <label class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-full border border-slate-300 text-slate-500 hover:bg-slate-50 dark:border-[#2D4060] dark:text-slate-300 dark:hover:bg-[#243352]" title="Send a voice note">
                <x-icon name="mic" class="h-5 w-5" />
                <input type="file" accept="audio/*" class="hidden" wire:model="voiceNote" wire:change="sendVoice">
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="send"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="send" class="h-5 w-5" />
            </button>
        </form>
        @error('draft') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        @error('voiceNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        <p wire:loading wire:target="sendVoice" class="text-xs text-slate-400">Sending your voice note…</p>
    </div>
</div>
