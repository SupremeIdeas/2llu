{{-- Customer chrome (blueprint Sections 4 & 12): a premium, responsive app
     shell — an Apple-inspired side menu on desktop and a bottom navigation
     with a centre "More" button on mobile. Dark-mode variants throughout. --}}
@php
    $u = auth()->user();

    // Core end-user destinations (bottom bar on mobile, top of the sidebar).
    $primary = [
        ['route' => 'dashboard', 'label' => 'Home', 'icon' => 'signal'],
        ['route' => 'catalogue', 'label' => 'eSIMs', 'icon' => 'globe'],
        ['route' => 'numbers', 'label' => 'Numbers', 'icon' => 'hash'],
        ['route' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet'],
    ];

    $more = [
        ['route' => 'referrals', 'label' => 'Referrals', 'icon' => 'gift'],
        ['route' => 'account', 'label' => 'Account', 'icon' => 'settings'],
    ];

    // Staff/admins use the same end-user app and can jump to their panel.
    if ($u && $u->hasAnyRole(['super_admin', 'admin', 'staff'])) {
        $more[] = ['route' => 'admin.dashboard', 'label' => 'Admin', 'icon' => 'id-card'];
    }
@endphp

<x-layouts.app :title="$title ?? config('app.name')">
    <x-app-shell :primary="$primary" :more="$more" brand-label="NaaraSim" brand-icon="signal" :brand-route="route('dashboard')">
        {{ $slot }}
    </x-app-shell>
</x-layouts.app>
