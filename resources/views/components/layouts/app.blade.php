<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'NaaraSim') }}</title>
    @if (! empty($description ?? null))
        <meta name="description" content="{{ $description }}">
        <meta property="og:title" content="{{ $title ?? config('app.name', 'NaaraSim') }}">
        <meta property="og:description" content="{{ $description }}">
        @if (! empty($ogImage ?? null))<meta property="og:image" content="{{ $ogImage }}">@endif
    @endif

    {{-- Favicon / app icon (Module 26): admin-uploaded if set, else the default. --}}
    @php($favicon = \App\Support\BrandSettings::favicon())
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
    @endif

    {{-- Preload brand fonts so headings/body paint without a flash. --}}
    <link rel="preload" href="/fonts/supreme-display.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="/fonts/didact-gothic.woff2" as="font" type="font/woff2" crossorigin>

    {{-- Pre-paint theme script: sets the `dark` class BEFORE first paint so
         there is no flash of the wrong theme (blueprint Section 4.2 / 24.3). --}}
    <script>
        (function () {
            // Reveal-on-scroll styles only apply when JS runs (no-JS visitors
            // and crawlers see everything immediately — Module 27).
            document.documentElement.classList.add('js-enabled');
            try {
                var stored = localStorage.getItem('theme');
                var wantsDark = stored
                    ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', wantsDark);
            } catch (e) { /* localStorage unavailable — default to light */ }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Runtime brand palette override (Module 26): recolours everything with no
         rebuild. Emitted only when the admin has customised a colour/radius. --}}
    @php($brandCss = \App\Support\BrandSettings::themeCss())
    @if ($brandCss)<style id="brand-vars">{!! $brandCss !!}</style>@endif
    @stack('head')
    @include('partials.tracking')
</head>
<body class="min-h-screen bg-[#F8F9FA] text-[#0F172A] antialiased dark:bg-navy dark:text-slate-100">
    @include('partials.icon-sprite')
    @include('partials.service-icon-sprite')
    <x-brand-preloader />
    <x-splash />
    <x-ui.toast-stack />
    {{ $slot ?? '' }}
    @yield('content')
    @livewireScripts
</body>
</html>
