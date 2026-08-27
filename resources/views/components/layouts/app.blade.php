@props(['pageType' => 'default'])
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

    {{-- Installable-app (PWA) hooks (App Export §1). The manifest is dynamic
         (admin-editable name/icon/colours). theme-color paints the mobile
         browser chrome + native WebView status bar. --}}
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="theme-color" content="{{ \App\Support\AppExport::get('theme_color', '#0A6E6E') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Support\AppExport::get('short_name', 'NaaraSim') }}">

    {{-- Preload the body font (compressed WOFF2) only. The display font loads via
         @font-face with font-display:swap — never preload the uncompressed TTF
         (it's a heavy download that blocks the critical path for no benefit). --}}
    <link rel="preload" href="/fonts/didact-gothic.woff2" as="font" type="font/woff2" crossorigin>

    {{-- Pre-paint theme script: sets the `dark` class BEFORE first paint so
         there is no flash of the wrong theme (blueprint Section 4.2 / 24.3).
         The user's choice lives in localStorage and MUST outlive navigation:
         Livewire `wire:navigate` morphs a fresh, server-rendered <html> (which
         has no `dark` class) into the page, so we re-apply the stored theme on
         every `livewire:navigated` too — otherwise dark mode would silently drop
         back to light the moment you open another page. --}}
    <script>
        (function () {
            // Reveal-on-scroll styles only apply when JS runs (no-JS visitors
            // and crawlers see everything immediately — Module 27).
            document.documentElement.classList.add('js-enabled');
            window.applyStoredTheme = function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var wantsDark = stored
                        ? stored === 'dark'
                        : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', wantsDark);
                } catch (e) { /* localStorage unavailable — default to light */ }
            };
            window.applyStoredTheme();
            // Re-assert the choice after each SPA navigation (see comment above).
            document.addEventListener('livewire:navigated', window.applyStoredTheme);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Runtime brand palette override (Module 26): recolours everything with no
         rebuild. Emitted only when the admin has customised a colour/radius. --}}
    @php($brandCss = \App\Support\BrandSettings::themeCss())
    @if ($brandCss)<style id="brand-vars">{!! $brandCss !!}</style>@endif
    {{-- Dashboard background / Platform Theme override (empty on the default
         treatment and outside the dashboard, where $bodyClass isn't set). --}}
    @isset($bodyClass)
        @php($platformCss = \App\Support\PlatformTheme::styleCss())
        @if ($platformCss)<style id="platform-theme-vars">{!! $platformCss !!}</style>@endif
    @endisset
    {{-- Active theme preset (Theme Batch 1): whitelisted :root overrides only.
         Empty string for naara-official (the built-in look already ships in
         app.css), so the default renders with zero injected CSS. Applies to
         BOTH the dashboard and the marketing site (which extends this layout). --}}
    @php($themeCss = \App\Support\ThemePreset::styleCss())
    @if ($themeCss)<style id="theme-preset-vars">{!! $themeCss !!}</style>@endif
    @stack('head')
    @include('partials.tracking')
</head>
<body class="min-h-screen text-[#0F172A] antialiased dark:text-slate-100 {{ \App\Support\ThemePreset::bodyClass() }} {{ $bodyClass ?? 'bg-[#F8F9FA] dark:bg-navy' }}">
    @include('partials.icon-sprite')
    @include('partials.service-icon-sprite')
    <x-brand-preloader :page-type="$pageType" />
    <x-splash />
    <x-ui.toast-stack />
    {{ $slot ?? '' }}
    @yield('content')
    @livewireScripts
</body>
</html>
