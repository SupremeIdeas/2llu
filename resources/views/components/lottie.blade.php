{{-- Self-hosted Lottie animation (resources/js/lottie.js hydrates every
     [data-lottie] node — runtime + JSON are lazy, CSP-safe, reduced-motion aware).
     `name` must match a key in the JS REGISTRY (gift-preloader | reward). --}}
@props(['name', 'loop' => true, 'autoplay' => true, 'label' => null])
<div
    data-lottie="{{ $name }}"
    data-lottie-loop="{{ $loop ? 'true' : 'false' }}"
    data-lottie-autoplay="{{ $autoplay ? 'true' : 'false' }}"
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
    {{ $attributes->merge(['class' => 'w-full h-full']) }}
></div>
