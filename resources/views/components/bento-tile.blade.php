{{-- Bento action tile (owner request). A compact bento card that replaces the
     old pill buttons: a 3D illustrated icon + a bold label, side by side and
     tappable. The icon + its opacity are admin-managed (Admin → Bento icons)
     via App\Support\BentoIcons, so they change with no redeploy.

     Renders an <a> when `href` is given (with wire:navigate), else a <button>.
     Every other directive — wire:click, @click, extra classes — passes straight
     through the attribute bag, so the caller keeps its exact click/nav logic.

     variant: "primary" = filled teal card · "default" = solid white/outlined.  --}}
@props(['bkey', 'label', 'href' => null, 'variant' => 'default'])
@php
    $icon = \App\Support\BentoIcons::icon($bkey);
    $op = \App\Support\BentoIcons::opacityFraction($bkey);
    // Admin-tunable size (Admin → Bento icons). Base 44px × scale, so the 3D
    // icons ship bold (default 1.6× = ~70px) and stay adjustable to taste.
    $iconPx = (int) round(44 * \App\Support\BentoIcons::scale($bkey));

    $base = 'nx-bento-tile group relative flex items-center gap-3 overflow-hidden rounded-2xl border p-4 text-left transition-all duration-300 hover:-translate-y-0.5';
    $skin = $variant === 'primary'
        ? 'border-primary/30 bg-primary/10 text-primary shadow-sm hover:bg-primary/15 hover:shadow-md dark:border-primary/40 dark:bg-primary/15 dark:text-teal-200'
        : 'border-slate-200 bg-white text-slate-800 shadow-sm hover:bg-slate-50 hover:shadow-md dark:border-white/10 dark:bg-[#16233d] dark:text-slate-100 dark:hover:bg-[#1b2c49]';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base.' '.$skin]) }}>
@else
    <button type="button" {{ $attributes->merge(['class' => $base.' '.$skin]) }}>
@endif
        {{-- Ambient brand glow (matches the numbers-section bento cards). --}}
        <span class="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-primary/5 blur-2xl transition-opacity duration-300 group-hover:bg-primary/15 dark:bg-teal-500/10 dark:group-hover:bg-teal-500/20" aria-hidden="true"></span>
        @if ($icon)
            {{-- Tightly-cropped transparent 3D icon — no badge container behind it.
                 Size is admin-tunable (base 44px × scale). --}}
            <img src="{{ $icon }}" alt="" aria-hidden="true"
                 class="relative shrink-0 object-contain transition-transform duration-300 group-hover:scale-105"
                 style="width: {{ $iconPx }}px; height: {{ $iconPx }}px; opacity: {{ $op }};">
        @endif
        <span class="relative min-w-0 flex-1 text-sm font-bold leading-tight sm:text-base">{{ $label }}</span>
@if ($href)
    </a>
@else
    </button>
@endif
