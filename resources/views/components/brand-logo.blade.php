{{-- Brand logo (Module 26 + HOTFIX §8). Renders the brand logo for the given
     variant (product = NaaraSim, family = Naara, gift = Naara Gift, agency =
     Supreme Ideas) — admin upload if set, else the shipped default
     (public/brand/*). Swaps light/dark by page theme by default; pass
     `theme="light"|"dark"` to FORCE one on a fixed-background surface.

     SIZING comes from the named `size` prop (sm|md|lg|xl) so the mark reads
     consistently everywhere — the same kind of place uses the same size. The
     `class` attribute is for one-off SPACING utilities (margins) only, applied
     to the wrapper, not for re-inventing the height per usage. --}}
@props(['variant' => 'product', 'size' => 'md', 'fallbackIcon' => 'signal', 'theme' => 'auto', 'label' => null])
@php
    $light = \App\Support\BrandSettings::resolvedLogo($variant, 'light');
    $dark = \App\Support\BrandSettings::resolvedLogo($variant, 'dark');
    // `label` names a sub-brand (e.g. "Naara Gift") for the alt text + wordmark
    // fallback; the platform brand name is the default.
    $name = $label ?: \App\Support\BrandSettings::name();

    // Named sizes → a fixed height + max-width pair, chosen once and reused.
    $sizes = [
        'sm' => 'h-7 max-w-[140px]',   // onboarding / compact
        'md' => 'h-8 max-w-[150px]',   // every top nav bar / mobile header
        'lg' => 'h-9 max-w-[170px]',   // sidebar brand, footer, auth panel
        'xl' => 'h-20 md:h-28',        // full-screen welcome entrance
    ];
    $sizeClass = 'w-auto object-contain '.($sizes[$size] ?? $sizes['md']);
@endphp
@if ($light || $dark)
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center']) }}>
        @if ($theme === 'light')
            <img src="{{ $light }}" alt="{{ $name }}" class="block {{ $sizeClass }}">
        @elseif ($theme === 'dark')
            <img src="{{ $dark }}" alt="{{ $name }}" class="block {{ $sizeClass }}">
        @else
            <img src="{{ $light }}" alt="{{ $name }}" class="block dark:hidden {{ $sizeClass }}">
            <img src="{{ $dark }}" alt="{{ $name }}" class="hidden dark:block {{ $sizeClass }}">
        @endif
    </span>
@else
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <x-icon name="{{ $fallbackIcon }}" class="h-6 w-6 text-primary" />
        <span class="font-display text-lg font-bold text-primary-dark dark:text-primary">{{ $name }}</span>
    </span>
@endif
