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
        ['route' => 'profile', 'label' => 'Profile', 'icon' => 'id-card'],
        ['route' => 'account', 'label' => 'Account', 'icon' => 'settings'],
        ['route' => 'security', 'label' => 'Security', 'icon' => 'shield'],
    ];

    // Developer portal — only surfaced when the operator has enabled the API.
    if (\App\Models\Setting::getValue('developer_api.enabled', false)) {
        array_splice($more, 4, 0, [['route' => 'developer', 'label' => 'Developer API', 'icon' => 'key']]);
    }

    // Active merchants get a link to their reseller storefront (ROADMAP §3.5).
    if ($u && $u->merchantAccount && $u->merchantAccount->isActive()) {
        array_unshift($more, ['route' => 'merchant.dashboard', 'label' => 'My Storefront', 'icon' => 'package']);
    }

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

    {{-- Merchant co-branding (ROADMAP §Layer 3.3): a subtle footer badge for
         customers who joined through a reseller — merchant mark + "Powered by
         NaaraSim". NaaraSim branding is never replaced, only accompanied. --}}
    @php($coBrand = \App\Support\MerchantBranding::forCustomer($u))
    @if ($coBrand)
        <div class="fixed bottom-24 left-4 z-30 hidden items-center gap-2 rounded-full border border-slate-200 bg-white/90 px-3 py-1.5 text-xs shadow-sm backdrop-blur lg:flex dark:border-[#2D4060] dark:bg-[#1A2840]/90">
            @if ($coBrand->logo_url)
                <img src="{{ $coBrand->logo_url }}" alt="{{ $coBrand->business_name }}" class="h-5 w-5 rounded object-contain">
            @else
                <span class="flex h-5 w-5 items-center justify-center rounded text-[9px] font-bold uppercase text-white" style="background-color: {{ $coBrand->brand_color ?: '#0A6E6E' }};">{{ \Illuminate\Support\Str::of($coBrand->business_name)->trim()->substr(0, 1) }}</span>
            @endif
            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $coBrand->business_name }}</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span class="text-slate-400 dark:text-slate-500">Powered by NaaraSim</span>
        </div>
    @endif
</x-layouts.app>
