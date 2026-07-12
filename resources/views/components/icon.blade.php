@props(['name', 'class' => 'w-5 h-5'])
@php
    // Admin custom-icon override (blueprint Section 16.2): if the admin has
    // mapped an image URL for this icon name, render the image; otherwise use
    // the built-in inline sprite symbol.
    $custom = \App\Support\IconOverrides::for($name);
@endphp
@if ($custom)
    <img src="{{ $custom }}" alt="{{ $name }}"
         {{ $attributes->merge(['class' => $class.' object-contain inline-block']) }}>
@else
    <svg {{ $attributes->merge(['class' => $class]) }} fill="none" aria-hidden="true">
        <use href="#i-{{ \Illuminate\Support\Str::slug($name) }}"></use>
    </svg>
@endif
