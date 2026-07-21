{{-- Payment-gateway brand marks (Module 27.5). Clean, recognizable, brand-
     coloured tiles — no more single-letter placeholders. Admin custom-icon
     overrides win (pay.<slug>), then the built-in inline SVG, then a fallback.
     Self-contained SVG (CSP-safe, no external requests). --}}
@props(['slug', 'class' => 'h-9 w-9'])
@php($override = \App\Support\IconOverrides::for('pay.'.$slug))
@if ($override)
    <img src="{{ $override }}" alt="{{ ucfirst($slug) }}" {{ $attributes->merge(['class' => $class.' rounded-lg object-contain']) }}>
@else
    <span {{ $attributes->merge(['class' => $class.' inline-flex shrink-0 items-center justify-center overflow-hidden rounded-lg']) }} role="img" aria-label="{{ ucfirst($slug) }}">
        @switch($slug)
            @case('paystack')
                {{-- Paystack: ascending cyan bars on deep navy. --}}
                <svg viewBox="0 0 36 36" class="h-full w-full"><rect width="36" height="36" rx="8" fill="#011B33"/><g fill="#00C3F7"><rect x="9" y="11" width="18" height="3.1" rx="1.55"/><rect x="9" y="16.45" width="18" height="3.1" rx="1.55"/><rect x="9" y="21.9" width="11.5" height="3.1" rx="1.55"/></g></svg>
                @break
            @case('flutterwave')
                {{-- Flutterwave: orange tile with the brand chevron/wave. --}}
                <svg viewBox="0 0 36 36" class="h-full w-full"><rect width="36" height="36" rx="8" fill="#FF7A00"/><path d="M11 12.5c4.6 0 6.2 3.4 8 6.6 1.4 2.5 2.6 4.4 5 4.4" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round"/><circle cx="12.4" cy="23.2" r="2.3" fill="#fff"/></svg>
                @break
            @case('stripe')
                {{-- Stripe: signature #635BFF tile with a white "S". --}}
                <svg viewBox="0 0 36 36" class="h-full w-full"><rect width="36" height="36" rx="8" fill="#635BFF"/><path d="M19.3 15.1c0-.9.75-1.25 1.95-1.25 1.75 0 3.95.53 5.7 1.48v-4.1a15 15 0 0 0-5.7-1.05c-4.65 0-7.75 2.43-7.75 6.5 0 6.35 8.7 5.33 8.7 8.07 0 1.06-.92 1.4-2.2 1.4-1.9 0-4.35-.78-6.28-1.84v4.16a15.9 15.9 0 0 0 6.28 1.31c4.77 0 8.05-2.36 8.05-6.48 0-6.85-8.75-5.63-8.75-8.21Z" fill="#fff"/></svg>
                @break
            @case('paypal')
                <svg viewBox="0 0 36 36" class="h-full w-full"><rect width="36" height="36" rx="8" fill="#F5F7FA"/><path d="M14.7 26.5h-2.9l2.4-15h6c3 0 5 1.5 4.5 4.6-.5 3.4-3 5-6.2 5h-2.5l-1.3 5.4Z" fill="#003087"/><path d="M17.3 24.5h-2.9l2.4-15h6c3 0 5 1.5 4.5 4.6-.5 3.4-3 5-6.2 5h-2.5l-1.3 5.4Z" fill="#009CDE" opacity=".85"/></svg>
                @break
            @case('binance')
                <svg viewBox="0 0 36 36" class="h-full w-full"><rect width="36" height="36" rx="8" fill="#0B0E11"/><g fill="#F0B90B"><path d="m18 9 2.6 2.6L18 14.2l-2.6-2.6L18 9Z"/><path d="m22.8 13.8 2.6 2.6L22.8 19l-2.6-2.6 2.6-2.6Z"/><path d="m13.2 13.8 2.6 2.6L13.2 19l-2.6-2.6 2.6-2.6Z"/><path d="m18 18.6 2.6 2.6L18 23.8l-2.6-2.6L18 18.6Z"/><path d="m8.4 16.4 1.8 1.8-1.8 1.8-1.8-1.8 1.8-1.8Zm19.2 0 1.8 1.8-1.8 1.8-1.8-1.8 1.8-1.8Z"/></g></svg>
                @break
            @default
                <span class="flex h-full w-full items-center justify-center bg-primary/10 font-display text-sm font-bold text-primary dark:bg-primary/20 dark:text-teal-300">{{ strtoupper(substr($slug, 0, 1)) }}</span>
        @endswitch
    </span>
@endif
