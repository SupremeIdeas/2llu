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

    // `More` is grouped with ['heading' => ...] separators so complementary
    // tools sit together; the app-shell renders headings in the desktop sidebar
    // and skips them in the mobile grid.
    if ($isPrivileged) {
        // Bottom-bar core differs slightly by role (super gets Staff, admin gets
        // Errors); the rest live in the "More" sheet / lower sidebar.
        $primary[] = ['route' => 'admin.pricing', 'label' => 'Pricing', 'icon' => 'credit-card'];
        $primary[] = $isSuper
            ? ['route' => 'admin.staff', 'label' => 'Staff', 'icon' => 'id-card']
            : ['route' => 'admin.errors', 'label' => 'Errors', 'icon' => 'file-text'];

        // Store & pricing.
        $more[] = ['heading' => 'Store & pricing'];
        $more[] = ['route' => 'admin.pricing-architect', 'label' => 'Price with Claude', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.coupons', 'label' => 'Coupons', 'icon' => 'gift'];
        $more[] = ['route' => 'admin.announcements', 'label' => 'Announcements', 'icon' => 'bell'];
        $more[] = ['route' => 'admin.banners', 'label' => 'Banners', 'icon' => 'image'];
        $more[] = ['route' => 'admin.esim-hero', 'label' => 'eSIM hero', 'icon' => 'signal'];
        $more[] = ['route' => 'admin.numbers-hero', 'label' => 'Numbers hero', 'icon' => 'phone'];
        $more[] = ['route' => 'admin.numbers-cards', 'label' => 'Numbers cards', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.credits', 'label' => 'NaaraCredits', 'icon' => 'gift'];

        // Money & partners.
        $more[] = ['heading' => 'Money & partners'];
        $more[] = ['route' => 'admin.payouts', 'label' => 'Payouts', 'icon' => 'credit-card'];
        $more[] = ['route' => 'admin.kyc', 'label' => 'Identity (KYC)', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.merchants', 'label' => 'Merchants', 'icon' => 'id-card'];
        $more[] = ['route' => 'admin.partners', 'label' => 'Partners', 'icon' => 'users'];
        $more[] = ['route' => 'admin.developer-api', 'label' => 'Developer API', 'icon' => 'key'];

        // Website (public front end + branding).
        $more[] = ['heading' => 'Website'];
        $more[] = ['route' => 'admin.builder', 'label' => 'Page builder', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.site', 'label' => 'Marketing site', 'icon' => 'globe'];
        $more[] = ['route' => 'admin.pages', 'label' => 'Custom pages', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.blog', 'label' => 'Blog', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.legal', 'label' => 'Legal', 'icon' => 'shield'];
        $more[] = ['route' => 'admin.branding', 'label' => 'Branding', 'icon' => 'image'];
        $more[] = ['route' => 'admin.chrome', 'label' => 'Auth & footer', 'icon' => 'image'];
        $more[] = ['route' => 'admin.appearance', 'label' => 'Splash', 'icon' => 'zap'];
        $more[] = ['route' => 'admin.dashboard-theme', 'label' => 'Dashboard theme', 'icon' => 'image'];
        $more[] = ['route' => 'admin.service-icons', 'label' => 'Service icons', 'icon' => 'grid'];
        $more[] = ['route' => 'admin.integrations', 'label' => 'Integrations', 'icon' => 'link'];
        $more[] = ['route' => 'admin.features', 'label' => 'Features', 'icon' => 'zap'];
    }

    // People & support — ticket-workers (staff scope) and admins.
    $support = [];
    if ($isPrivileged) {
        $support[] = ['route' => 'admin.users', 'label' => 'Users', 'icon' => 'id-card'];
        $support[] = ['route' => 'admin.support-agent', 'label' => 'Support agent', 'icon' => 'message-circle'];
        $support[] = ['route' => 'admin.deletions', 'label' => 'Deletions', 'icon' => 'trash'];
    }
    if ($isPrivileged || $u->can('tickets.manage')) {
        $support[] = ['route' => 'admin.tickets', 'label' => 'Tickets', 'icon' => 'message-circle'];
    }
    if ($support) {
        $more[] = ['heading' => 'People & support'];
        $more = array_merge($more, $support);
    }

    // System — super-admin only.
    if ($isSuper) {
        $more[] = ['heading' => 'System'];
        $more[] = ['route' => 'admin.api-keys', 'label' => 'API keys', 'icon' => 'key'];
        $more[] = ['route' => 'admin.email', 'label' => 'Email', 'icon' => 'mail'];
        $more[] = ['route' => 'admin.errors', 'label' => 'Error log', 'icon' => 'file-text'];
        $more[] = ['route' => 'admin.backups', 'label' => 'Backups', 'icon' => 'package'];
        $more[] = ['route' => 'admin.maintenance', 'label' => 'Maintenance', 'icon' => 'refresh'];
        $more[] = ['route' => 'admin.ui-kit', 'label' => 'UI Kit', 'icon' => 'grid'];
    }

    $primary[] = ['route' => 'admin.account', 'label' => 'My account', 'icon' => 'id-card'];
    $primary[] = ['route' => 'admin.security', 'label' => 'Security', 'icon' => 'shield'];
    // Everyone in the panel can hop back to the end-user app.
    $more[] = ['heading' => 'Shortcuts'];
    $more[] = ['route' => 'dashboard', 'label' => 'Storefront', 'icon' => 'globe'];
@endphp

<x-layouts.app :title="($title ?? 'Admin').' — NaaraSim'" :body-class="\App\Support\PlatformTheme::bodyClass()">
    <x-app-shell :primary="$primary" :more="$more" brand-label="NaaraSim Admin" brand-icon="settings" :brand-route="route('admin.dashboard')">
        {{ $slot }}
    </x-app-shell>

    {{-- One modal engine for every API-key help icon (Section 15.1). --}}
    <livewire:admin.api-guide-modal />
</x-layouts.app>
