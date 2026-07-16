{{-- Global toast notifications (Module 32). Included ONCE in the base layout.
     Fire from Livewire:  $this->dispatch('nx-toast', type: 'success', message: '…');
     or from JS:          window.dispatchEvent(new CustomEvent('nx-toast', {detail:{type:'info',message:'…'}}))
     Types: success | error | info. Auto-dismisses; hover pauses forever-stack
     growth by capping at 4. Accessible via role="status" + aria-live. --}}
<div x-data="{
        toasts: [],
        push(detail) {
            const t = { id: Date.now() + Math.random(), type: detail.type || 'info', message: detail.message || '' };
            this.toasts.push(t);
            if (this.toasts.length > 4) this.toasts.shift();
            setTimeout(() => this.dismiss(t.id), 4500);
        },
        dismiss(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
     }"
     x-on:nx-toast.window="push($event.detail)"
     class="pointer-events-none fixed bottom-4 right-4 z-[90] flex flex-col items-end gap-2"
     aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div class="nx-toast pointer-events-auto"
             :class="{ 'nx-toast--success': t.type === 'success', 'nx-toast--error': t.type === 'error', 'nx-toast--info': t.type === 'info' }"
             role="status">
            <svg x-show="t.type === 'success'" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <svg x-show="t.type === 'error'" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <svg x-show="t.type === 'info'" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <span class="min-w-0 flex-1" x-text="t.message"></span>
            <button type="button" class="shrink-0 opacity-60 transition hover:opacity-100" x-on:click="dismiss(t.id)" aria-label="Dismiss notification">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    </template>
</div>
