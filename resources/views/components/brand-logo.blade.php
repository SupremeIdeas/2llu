{{-- Brand logo (Module 26). Renders the brand logo for the given variant
     (product = NaaraSim, agency = Supreme Ideas Agency) — admin upload if set,
     else the shipped default (public/brand/*). By default it swaps light/dark by
     page theme; pass `theme="light"` or `theme="dark"` to FORCE one variant on a
     surface whose background is fixed regardless of theme (e.g. the navy footer
     or the auth media panel), so the mark always has proper contrast. --}}
@props(['variant' => 'product', 'class' => 'h-8', 'fallbackIcon' => 'signal', 'theme' => 'auto', 'label' => null])
@php
    $light = \App\Support\BrandSettings::resolvedLogo($variant, 'light');
    $dark = \App\Support\BrandSettings::resolvedLogo($variant, 'dark');
    // `label` names a sub-brand (e.g. "Naara Gift") for the alt text + wordmark
    // fallback; the platform brand name is the default.
    $name = $label ?: \App\Support\BrandSettings::name();
@endphp
@if ($light || $dark)
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center']) }}>
        @if ($theme === 'light')
            <img src="{{ $light }}" alt="{{ $name }}" class="block w-auto object-contain {{ $class }}">
        @elseif ($theme === 'dark')
            <img src="{{ $dark }}" alt="{{ $name }}" class="block w-auto object-contain {{ $class }}">
        @else
            <img src="{{ $light }}" alt="{{ $name }}" class="block w-auto object-contain dark:hidden {{ $class }}">
            <img src="{{ $dark }}" alt="{{ $name }}" class="hidden w-auto object-contain dark:block {{ $class }}">
        @endif
    </span>
@else
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <x-icon name="{{ $fallbackIcon }}" class="h-6 w-6 text-primary" />
        <span class="font-display text-lg font-bold text-primary-dark dark:text-primary">{{ $name }}</span>
    </span>
@endif
