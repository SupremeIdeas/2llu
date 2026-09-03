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
    /** All wallet-funding gateways (slug => [label, hint]). */
    public const GATEWAYS = [
        'paystack' => ['Paystack', 'Cards, bank transfer & USSD'],
        'flutterwave' => ['Flutterwave', 'Cards, mobile money & banks'],
        'stripe' => ['Stripe', 'International cards (USD)'],
        'paypal' => ['PayPal', 'PayPal balance & cards'],
        'binance' => ['Binance Pay', 'Pay with crypto — USDT & more'],
        'nowpayments' => ['NOWPayments', 'Bitcoin, USDT & 100+ coins'],
        'cryptomus' => ['Cryptomus', 'Crypto wallet & exchange'],
        'coinpayments' => ['CoinPayments', 'Bitcoin & altcoins'],
        'payssion' => ['Payssion', 'Local payment methods'],
    ];

    #[Validate('required|numeric|min:1')]
    public $amount = '';

    #[Validate('required|in:paystack,flutterwave,stripe,paypal,binance,nowpayments,cryptomus,coinpayments,payssion')]
    public string $gateway = 'paystack';

    /** The currency the user PAYS in. USD/NGN credit their wallet column directly;
     *  any other supported currency is converted to a locked USD credit. */
    #[Validate('required|in:USD,NGN,GHS,KES,ZAR,GBP,EUR,CAD,INR')]
    public string $currency = 'NGN';

    public ?string $error = null;

    /** The currency the user sees prices in (display only; USD is settlement). */
    public string $displayCurrency = 'USD';

    public function mount(): void
    {
        // Default to the first Active gateway so the selector never opens on a
        // "Coming Soon" one; falls back to the first known if none configured yet.
        $active = array_keys($this->availableGateways());
        $this->gateway = $active[0] ?? array_key_first(self::GATEWAYS);

        $this->displayCurrency = \App\Support\LocaleCurrency::resolve(auth()->user());
    }

    /** Switch the display currency (persists to session + profile). */
    public function setCurrency(string $code): void
    {
        $this->displayCurrency = \App\Support\LocaleCurrency::choose(auth()->user(), $code);
    }

    /** Gateways the admin has configured (keys present) — the selectable set. */
    public function availableGateways(): array
    {
        return array_filter(
            self::GATEWAYS,
            fn ($slug) => \App\Support\ProviderStatus::isActive($slug),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function topUp()
    {
        $this->validate();
        $this->error = null;

        $user = auth()->user();
        $amount = (float) $this->amount;
        $currency = strtoupper($this->currency);

        try {
            $result = app("pay.{$this->gateway}")->initialize($user, $amount, $currency);
        } catch (\Throwable $e) {
            $this->error = 'We could not start the payment. Please try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Top-up could not start',
                message: 'We couldn’t reach the payment provider — you were not charged. Please try again.');

            return null;
        }

        // Local-currency deposit (owner request, money-safe): USD and NGN credit
        // their wallet column directly (unchanged). Any OTHER currency is
        // converted to a USD credit LOCKED here at the live rate — recorded on a
        // TopUpIntent so the webhook credits exactly this, never a figure
        // re-derived from the gateway's reported currency.
        if (! in_array($currency, ['USD', 'NGN'], true) && ! empty($result['reference'])) {
            $fx = app(\App\Services\Pricing\CurrencyService::class);
            \App\Models\TopUpIntent::create([
                'user_id' => $user->id,
                'gateway' => $this->gateway,
                'reference' => $result['reference'],
                'charge_amount' => $amount,
                'charge_currency' => $currency,
                'usd_amount' => $fx->toUsd($amount, $currency),
                'rate_usd_to_local' => $fx->rate($currency),
                'status' => 'pending',
            ]);
        }

        return redirect()->away($result['redirect_url']);
    }

    public function render()
    {
        $user = auth()->user();
        $wallet = $user->wallet ?? new UserWallet(['ngn_balance' => 0, 'usd_balance' => 0]);
        $transactions = $user->walletTransactions()->latest()->limit(20)->get();

        // "My Spending" card (Module 32 pick — Gidarx aurora card, made real):
        // this-month sums + a 14-day USD spend sparkline, all from the user's
        // own wallet_transactions. Purchases are charged in USD; top-ups can be
        // NGN or USD, so both are shown.
        $recent = $user->walletTransactions()
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->get(['type', 'amount', 'currency', 'created_at']);
        $month = $recent->where('created_at', '>=', now()->startOfMonth());

        $spentUsd = (float) $month->where('type', 'debit')->where('currency', 'USD')->sum('amount')
            - (float) $month->where('type', 'refund')->where('currency', 'USD')->sum('amount');
        $topupUsd = (float) $month->where('type', 'credit')->where('currency', 'USD')->sum('amount');
        $topupNgn = (float) $month->where('type', 'credit')->where('currency', 'NGN')->sum('amount');

        $daily = collect(range(13, 0))->map(function ($back) use ($recent) {
            $day = now()->subDays($back)->toDateString();

            return (float) $recent
                ->filter(fn ($t) => $t->type === 'debit' && $t->currency === 'USD' && $t->created_at->toDateString() === $day)
                ->sum('amount');
        })->values();

        // Normalise into SVG polyline points (viewBox 0 0 200 48, baseline y=44).
        $peak = max((float) $daily->max(), 0.01);
        $sparkline = $daily->map(
            fn ($v, $i) => round($i * (200 / 13), 1).','.round(44 - ($v / $peak) * 36, 1)
        )->implode(' ');

        return view('livewire.wallet', compact(
            'wallet', 'transactions', 'spentUsd', 'topupUsd', 'topupNgn', 'sparkline'
        ) + [
            'hasSpendData' => $daily->sum() > 0,
            'gateways' => $this->availableGateways(),
            // Localized display (owner request): the USD balance shown in the
            // user's local currency too. Display only — the wallet holds USD/NGN.
            'currencyOptions' => \App\Support\LocaleCurrency::options(),
            'usdLocal' => $this->displayCurrency === 'USD' || $this->displayCurrency === 'NGN'
                ? null
                : app(\App\Services\Pricing\CurrencyService::class)->format((float) $wallet->usd_balance, $this->displayCurrency),
        ]);
    }
}
