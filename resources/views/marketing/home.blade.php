<x-layouts.marketing>
    @foreach ($sections as $key => $s)
        @includeIf('marketing.home.'.$key, ['s' => $s])
    @endforeach

    {{-- Sticky CTA pill — appears once the hero scrolls away. --}}
    <div class="mkt-sticky-cta">
        <a href="{{ auth()->check() ? route('catalogue') : route('register') }}" class="nx-btn nx-btn--gold shadow-2xl">
            <x-icon name="globe" class="h-4 w-4" /> {{ $sections['hero']['cta_primary'] ?? 'Get Your eSIM Now' }}
        </a>
    </div>
</x-layouts.marketing>
