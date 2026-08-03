<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\PaymentGatewayConfig;
use App\Support\PaymentSandbox;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Payments → Gateways (BUILD-2 §3). Per payment gateway: the
 * sandbox/live mode toggle (swaps the base URL where the hosts differ), the
 * read-only webhook + callback URLs to register on the provider dashboard, and
 * a live test-connection button. Keys live on Admin → Provider Keys (one
 * credential system). Super-admin / admin only.
 */
#[Layout('components.layouts.admin')]
class Gateways extends Component
{
    /** Live test-connection results, keyed by gateway. */
    public array $probe = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function setMode(string $gateway, string $mode): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(PaymentGatewayConfig::isGateway($gateway), 422);

        PaymentGatewayConfig::setMode($gateway, $mode);
        Auditor::log('payments.mode_changed', null, null, ['gateway' => $gateway, 'mode' => $mode]);
        $this->dispatch('nx-toast', type: $mode === 'sandbox' ? 'info' : 'success',
            message: PaymentGatewayConfig::GATEWAYS[$gateway]['label'].' set to '.strtoupper($mode).' mode.');
    }

    public function test(string $gateway): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(PaymentGatewayConfig::isGateway($gateway), 422);

        $result = PaymentGatewayConfig::testConnection($gateway);
        $this->probe[$gateway] = $result;
        $this->dispatch('nx-toast', type: $result['ok'] ? 'success' : 'error', message: $result['message']);
    }

    public function render()
    {
        $gateways = collect(PaymentGatewayConfig::GATEWAYS)->map(fn ($meta, $gw) => [
            'key' => $gw,
            'label' => $meta['label'],
            'configured' => ProviderStatus::isActive($gw),
            'mode' => PaymentGatewayConfig::mode($gw),
            'has_hosts' => PaymentGatewayConfig::hasDistinctHosts($gw),
            'is_test' => PaymentSandbox::isTest($gw),
            'testable' => $meta['testable'],
            'webhook_url' => PaymentGatewayConfig::webhookUrl($gw),
            'callback_url' => PaymentGatewayConfig::callbackUrl(),
            'probe' => $this->probe[$gw] ?? null,
        ])->values()->all();

        return view('livewire.admin.gateways', compact('gateways'));
    }
}
