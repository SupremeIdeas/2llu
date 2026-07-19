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
        ['route' => 'support', 'label' => 'Help & Support', 'icon' => 'message-circle'],
        ['route' => 'rewards', 'label' => 'Rewards', 'icon' => 'gift'],
        ['route' => 'referrals', 'label' => 'Referrals', 'icon' => 'gift'],
        ['route' => 'data-estimator', 'label' => 'Estimator', 'icon' => 'signal'],
        ['route' => 'account', 'label' => 'Account', 'icon' => 'settings'],
        ['route' => 'security', 'label' => 'Security', 'icon' => 'shield'],
    ];

    // Staff/admins use the same end-user app and can jump to their panel.
    if ($u && $u->hasAnyRole(['super_admin', 'admin', 'staff'])) {
        $more[] = ['route' => 'admin.dashboard', 'label' => 'Admin', 'icon' => 'id-card'];
    }
@endphp

<x-layouts.app :title="$title ?? config('app.name')">
    <x-app-shell :primary="$primary" :more="$more" :promo="true" brand-label="NaaraSim" brand-icon="signal" :brand-route="route('dashboard')">
        {{ $slot }}
    </x-app-shell>

    {{-- Live-help: WhatsApp support (blueprint Section 32). Stacked above the
         NaaraSim Wizard launcher so the two floating actions never overlap. --}}
    @if (\App\Support\Niche\SupportLinks::hasWhatsapp())
        <a href="{{ \App\Support\Niche\SupportLinks::whatsappUrl() }}" target="_blank" rel="noopener"
           aria-label="Chat with support on WhatsApp"
           class="fixed bottom-40 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition hover:bg-primary-dark lg:bottom-24">
            <x-icon name="message-circle" class="h-6 w-6" />
        </a>
    @endif

    {{-- NaaraSim Wizard — guided, buttons-only purchase widget (roadmap §3/§11).
         Only rendered for verified end-users (this layout is behind auth). --}}
    @livewire('wizard')
</x-layouts.app>
