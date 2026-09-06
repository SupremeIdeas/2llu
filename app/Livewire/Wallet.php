<?php

namespace App\Livewire;

use App\Models\PayoutAccount;
use App\Models\TopUpIntent;
use App\Models\UserWallet;
use App\Services\Payouts\PayoutThreshold;
use App\Services\Payouts\WithdrawalService;
use App\Services\Pricing\CurrencyService;
use App\Support\GatewayCurrencyMatrix;
use App\Support\LocaleCurrency;
use App\Support\ProviderStatus;
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

        $this->displayCurrency = LocaleCurrency::resolve(auth()->user());

        // Gateway/currency orchestration (Part B §3.6): never boot into a
        // pairing the chosen gateway doesn't actually accept.
        if (! GatewayCurrencyMatrix::supports($this->gateway, $this->currency)) {
            $this->currency = GatewayCurrencyMatrix::currenciesFor($this->gateway)[0] ?? $this->currency;
        }
    }

    /** Switch the display currency (persists to session + profile). */
    public function setCurrency(string $code): void
    {
        $this->displayCurrency = LocaleCurrency::choose(auth()->user(), $code);
    }

    /** Gateways the admin has configured (keys present) — the selectable set. */
    public function availableGateways(): array
    {
        return array_filter(
            self::GATEWAYS,
            fn ($slug) => ProviderStatus::isActive($slug),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Gateways from availableGateways() that also accept the currently
     * selected TOP-UP currency (Part B §3.6) — the UI never offers a
     * gateway/currency pairing that gateway doesn't actually accept.
     */
    public function payGateways(): array
    {
        return array_filter(
            $this->availableGateways(),
            fn ($slug) => GatewayCurrencyMatrix::supports($slug, $this->currency),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** Currency changed: if the current gateway no longer accepts it, switch
     *  to the first active gateway that does (never silently keep an invalid
     *  pairing selected). */
    public function updatedCurrency(): void
    {
        $this->currency = strtoupper($this->currency);
        if (GatewayCurrencyMatrix::supports($this->gateway, $this->currency)) {
            return;
        }
        $valid = array_keys($this->payGateways());
        if ($valid !== []) {
            $this->gateway = $valid[0];
        }
    }

    /** Gateway changed: if it doesn't accept the current currency, switch to
     *  the first currency it does accept. */
    public function updatedGateway(): void
    {
        if (GatewayCurrencyMatrix::supports($this->gateway, $this->currency)) {
            return;
        }
        $this->currency = GatewayCurrencyMatrix::currenciesFor($this->gateway)[0] ?? $this->currency;
    }

    public function topUp()
    {
        $this->validate();
        $this->error = null;

        $user = auth()->user();
        $amount = (float) $this->amount;
        $currency = strtoupper($this->currency);

        // Server-side validation (Part B §3.6) — never rely on the UI filter
        // alone; reject fast and legibly rather than letting the provider
        // fail on a pairing it never accepted.
        if (! GatewayCurrencyMatrix::supports($this->gateway, $currency)) {
            $this->error = ucfirst($this->gateway)." doesn't accept {$currency}. Please pick a different gateway or currency.";

            return null;
        }

        try {
            $result = app("pay.{$this->gateway}")->initialize($user, $amount, $currency);
        } catch (\Throwable $e) {
            $this->error = 'We could not start the payment. Please try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Top-up could not start',
                message: 'We couldn’t reach the payment provider — you were not charged. Please try again.');

            return null;
        }

        // Unified USD Wallet (Part B): USD credits directly. Every OTHER
        // currency — NGN included — is converted to a USD credit LOCKED here
        // at the live rate, recorded on a TopUpIntent so the webhook credits
        // exactly this, never a figure re-derived from the gateway's reported
        // currency. NGN used to be a second directly-credited currency; it no
        // longer is — usd_balance is the one spendable balance.
        if ($currency !== 'USD' && ! empty($result['reference'])) {
            $fx = app(CurrencyService::class);
            TopUpIntent::create([
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

    public function render(WithdrawalService $withdrawals, PayoutThreshold $threshold)
    {
        $user = auth()->user();
        $wallet = $user->wallet ?? new UserWallet(['ngn_balance' => 0, 'usd_balance' => 0]);
        $transactions = $user->walletTransactions()->latest()->limit(20)->get();

        // Payout/withdraw summary (owner request: surface it prominently on the
        // wallet page itself rather than only at the separate /rewards/withdraw
        // route). Free-payout/KYC threshold check reuses the SAME service
        // WithdrawalService::request() uses server-side — this is a display
        // decision, not the security boundary. Bank-account setup below is
        // always free; only the withdraw button itself is threshold-gated.
        $requiresKyc = $threshold->requiresKyc($user);
        $canWithdraw = $threshold->canWithdraw($user);
        $payoutAccount = PayoutAccount::where('user_id', $user->id)->where('is_default', true)->first();
        $withdrawableUsd = $withdrawals->availableUsd($user);

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

        $fx = app(CurrencyService::class);

        return view('livewire.wallet', compact(
            'wallet', 'transactions', 'spentUsd', 'topupUsd', 'topupNgn', 'sparkline',
            'requiresKyc', 'canWithdraw', 'payoutAccount', 'withdrawableUsd'
        ) + [
            'hasSpendData' => $daily->sum() > 0,
            // Part B §3.6: only gateways that accept the CURRENTLY selected
            // top-up currency — never a pairing the gateway doesn't support.
            'gateways' => $this->payGateways(),
            // The full top-up currency picker (owner request: a real dropdown,
            // not a hardcoded NGN/USD pair) — every currency any configured
            // gateway accepts, restricted to what CurrencyService models.
            'payCurrencyOptions' => collect(GatewayCurrencyMatrix::allCurrencies())
                ->mapWithKeys(fn ($code) => [$code => CurrencyService::SUPPORTED[$code][1]])
                ->all(),
            // Localized display (owner request): the USD balance shown in the
            // user's local currency too. Display only — the wallet holds USD/NGN.
            'currencyOptions' => LocaleCurrency::options(),
            'usdLocal' => $this->displayCurrency === 'USD' || $this->displayCurrency === 'NGN'
                ? null
                : $fx->format((float) $wallet->usd_balance, $this->displayCurrency),
            // Part B code change 6: ngn_balance is now a legacy/historical
            // figure only (no new top-up ever credits it) — the live NGN
            // figure shown alongside the spendable USD balance is always the
            // CURRENT-RATE equivalent, never the frozen historical column.
            'ngnLive' => $fx->format((float) $wallet->usd_balance, 'NGN'),
        ]);
    }
}
