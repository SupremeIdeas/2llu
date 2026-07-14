{{-- Brand logo (Module 26). Renders the admin-uploaded logo for the given
     variant (product = NaaraSim, agency = Supreme Ideas Agency), swapping the
     light/dark version by theme. Falls back to the built-in icon + wordmark so a
     fresh install still looks intentional. --}}
@props(['variant' => 'product', 'class' => 'h-8', 'fallbackIcon' => 'signal'])
@php
    $light = \App\Support\BrandSettings::logo($variant, 'light') ?? \App\Support\BrandSettings::logo($variant, 'dark');
    $dark = \App\Support\BrandSettings::logo($variant, 'dark') ?? \App\Support\BrandSettings::logo($variant, 'light');
    $name = \App\Support\BrandSettings::name();
@endphp
@if ($light || $dark)
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center']) }}>
        <img src="{{ $light }}" alt="{{ $name }}" class="block w-auto object-contain dark:hidden {{ $class }}">
        <img src="{{ $dark }}" alt="{{ $name }}" class="hidden w-auto object-contain dark:block {{ $class }}">
    </span>
@else
    <span {{ $attributes->only('class')->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <x-icon name="{{ $fallbackIcon }}" class="h-6 w-6 text-primary" />
        <span class="font-display text-lg font-bold text-primary-dark dark:text-primary">{{ $name }}</span>
    </span>
@endif
