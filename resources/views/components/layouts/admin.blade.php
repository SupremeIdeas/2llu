{{-- Admin chrome (blueprint Sections 13, 15, 17, 25, 27). Same premium shell as
     the customer app — Apple-inspired side menu on desktop, mobile bottom nav —
     but role-scoped: staff see only what they may use; admin config is
     super_admin/admin; staff management is super_admin only. --}}
@php
    $u = auth()->user();
    $isPrivileged = $u->hasAnyRole(['super_admin', 'admin']);
    $isSuper = $u->hasRole('super_admin');

    $primary = [['route' => 'admin.dashboard', 'label' => 'Overview', 'icon' => 'signal']];
    $more = [];

    if ($isPrivileged) {
        // Bottom-bar core differs slightly by role (super gets Staff, admin gets
        // Errors); the rest live in the "More" sheet / lower sidebar.
        $primary[] = ['route' => 'admin.pricing', 'label' => 'Pricing', 'icon' => 'credit-card'];
        $primary[] = $isSuper
            ? ['route' => 'admin.staff', 'label' => 'Staff', 'icon' => 'id-card']
            : ['route' => 'admin.errors', 'label' => 'Errors', 'icon' => 'file-text'];

        $more[] = ['route' => 'admin.appearance', 'label' => 'Splash', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.deletions', 'label' => 'Deletions', 'icon' => 'trash'];
        if ($isSuper) {
            $more[] = ['route' => 'admin.errors', 'label' => 'Error log', 'icon' => 'file-text'];
        }
    }

    $primary[] = ['route' => 'admin.security', 'label' => 'Security', 'icon' => 'shield'];
    // Everyone in the panel can hop back to the end-user app.
    $more[] = ['route' => 'dashboard', 'label' => 'Storefront', 'icon' => 'globe'];
@endphp

<x-layouts.app :title="($title ?? 'Admin').' — NaaraSim'">
    <x-app-shell :primary="$primary" :more="$more" brand-label="NaaraSim Admin" brand-icon="settings" :brand-route="route('admin.dashboard')">
        {{ $slot }}
    </x-app-shell>

    {{-- One modal engine for every API-key help icon (Section 15.1). --}}
    <livewire:admin.api-guide-modal />
</x-layouts.app>
