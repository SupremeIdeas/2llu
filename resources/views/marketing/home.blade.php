<x-layouts.marketing>
    @foreach ($sections as $key => $s)
        {{-- Page-specific partial wins; a portable/reused section falls back to
             the shared `marketing.sections.*` partial so it renders anywhere. --}}
        @includeFirst(['marketing.home.'.$key, 'marketing.sections.'.$key], ['s' => $s])

        {{-- Decorative, non-CMS sections injected at fixed anchors so the admin's
             section ordering stays intact. --}}
        @if ($key === 'hero')
            @include('marketing.home._flags')
            {{-- Homepage video (BUILD-3 §8) then story (§9), after the flag
                 carousel — both admin-editable, and self-hiding when unset. --}}
            @include('marketing.home._video')
            @include('marketing.home._story')
        @endif
        @if ($key === 'how')
            @include('marketing.home._wizard')
        @endif
    @endforeach

</x-layouts.marketing>
