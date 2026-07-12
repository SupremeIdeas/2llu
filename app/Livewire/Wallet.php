<?php

namespace App\Livewire;

use App\Models\UserWallet;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Wallet (blueprint Sections 14.2). Shows dual-currency balances and history,
 * and starts a top-up via a payment gateway (Flutterwave/Paystack/Stripe).
 * Payment initialization is synchronous because it must return a redirect URL.
 */
#[Layout('components.layouts.customer')]
class Wallet extends Component
{
    #[Validate('required|numeric|min:1')]
    public $amount = '';

    #[Validate('required|in:paystack,flutterwave,stripe')]
    public string $gateway = 'paystack';

    #[Validate('required|in:NGN,USD')]
    public string $currency = 'NGN';

    public ?string $error = null;

    public function topUp()
    {
        $this->validate();
        $this->error = null;

        try {
            $result = app("pay.{$this->gateway}")
                ->initialize(auth()->user(), (float) $this->amount, $this->currency);
        } catch (\Throwable $e) {
            $this->error = 'We could not start the payment. Please try again.';

            return null;
        }

        return redirect()->away($result['redirect_url']);
    }

    public function render()
    {
        $user = auth()->user();
        $wallet = $user->wallet ?? new UserWallet(['ngn_balance' => 0, 'usd_balance' => 0]);
        $transactions = $user->walletTransactions()->latest()->limit(20)->get();

        return view('livewire.wallet', compact('wallet', 'transactions'));
    }
}
