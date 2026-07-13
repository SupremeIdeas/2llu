{{-- Admin chrome (blueprint Sections 13, 15, 17, 25). Admin-only, mounted on
     the env-driven admin path; the `admin` middleware enforces the IP
     allow-list, a plain 404 for non-admins, and TOTP 2FA. --}}
<x-layouts.app :title="($title ?? 'Admin').' — NaaraSim'">
    <div class="min-h-screen">
        <nav class="border-b border-slate-200 bg-white dark:border-[#2D4060] dark:bg-[#1A2840]">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-bold text-primary-dark dark:text-primary">
                    <x-icon name="settings" class="h-6 w-6 text-primary" />
                    <span>NaaraSim Admin</span>
                </a>
                <div class="hidden items-center gap-1 sm:flex">
                    @php
                        $links = [
                            'admin.dashboard' => ['Overview', 'signal'],
                            'admin.pricing' => ['Pricing', 'credit-card'],
                            'admin.errors' => ['Error log', 'file-text'],
                            'admin.appearance' => ['Splash', 'zap'],
                            'admin.security' => ['Security', 'shield'],
                        ];
                    @endphp
                    @foreach ($links as $route => [$label, $icon])
                        <a href="{{ route($route) }}"
                           @class([
                               'flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                               'bg-primary/10 text-primary-dark dark:bg-primary/20 dark:text-primary' => request()->routeIs($route),
                               'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#243352]' => ! request()->routeIs($route),
                           ])>
                            <x-icon :name="$icon" class="h-4 w-4" /> <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
                <x-theme-toggle />
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>
    </div>

    {{-- One modal engine for every API-key help icon (Section 15.1). --}}
    <livewire:admin.api-guide-modal />
</x-layouts.app>
