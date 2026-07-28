<x-layouts.marketing>
    @foreach ($sections as $key => $s)
        @includeIf('marketing.home.'.$key, ['s' => $s])

        {{-- Decorative, non-CMS sections injected at fixed anchors so the admin's
             section ordering stays intact. --}}
        @if ($key === 'hero')
            @include('marketing.home._flags')
        @endif
        @if ($key === 'how')
            @include('marketing.home._wizard')
        @endif
    @endforeach

</x-layouts.marketing>
