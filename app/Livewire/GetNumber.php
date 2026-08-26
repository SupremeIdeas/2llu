<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Models\SmsOrder;
use App\Notifications\OrderPlacedNotification;
use App\Services\Credits\CreditService;
use App\Services\Merchants\MerchantEarningsService;
use App\Services\Pricing\CouponEngine;
use App\Services\Pricing\PricingEngine;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\PermanentNumberRouter;
use App\Services\SMS\SmsNumberRouter;
use App\Services\Wallet\WalletService;
use App\Services\WhatsApp\WhatsAppAutopilot;
use App\Support\CountryNames;
use App\Support\CreditSettings;
use App\Support\Mailer;
use App\Support\MerchantBranding;
use App\Support\NumberCatalogue;
use App\Support\PendingCoupon;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Get-a-Number flow (blueprint Section 12.1). Country + service + type, routed
 * by the capability router. Debits the live retail, orders through the lane,
 * then polls for the code. The user never sees a provider name or a cost.
 */
#[Layout('components.layouts.customer', ['preloaderType' => 'numbers'])]
class GetNumber extends Component
{
    public string $country = 'usa';

    public string $service = 'whatsapp';

    public string $type = 'otp';

    /** Which product modal is open ('', 'verify', 'rent', 'line') — deep-linkable. */
    #[Url(as: 'modal')]
    public string $modal = '';

    /** Verify modal: 'manual' (pick each step) or 'smart' (auto-best). */
    public string $buyMode = 'manual';

    /** Friendly names for the current picks (from the shared pickers). */
    public string $serviceName = 'WhatsApp';

    public string $countryName = 'United States';

    /** Rent modal: rental period in hours (pills). */
    public int $rentHours = 168;

    public ?int $orderId = null;

    public ?string $error = null;

    /** Coupon (Module 31) — applied to the live retail quote, floor-clamped. */
    public string $coupon = '';

    public ?string $couponNote = null;

    /** Client-side filter for the (large) service picker. */
    public string $serviceSearch = '';

    public function mount(): void
    {
        // Pre-fill a coupon claimed from an offer (one-tap path); still validated
        // + MarginGuard-clamped when applied.
        $this->coupon = PendingCoupon::peek() ?? '';

        // Deep-linked modal (e.g. ?modal=line from "Get a Naara Line"): apply the
        // same request-type default openModal() would, and drop an unknown value.
        if ($this->modal !== '') {
            if (! in_array($this->modal, ['verify', 'rent', 'line'], true)) {
                $this->modal = '';
            } else {
                $this->type = match ($this->modal) {
                    'rent' => NumberRequest::TYPE_RENTAL,
                    'line' => NumberRequest::TYPE_PERMANENT,
                    default => NumberRequest::TYPE_OTP,
                };
            }
        }
    }

    /** Switching away from rental clears an "any service" (full-rent) pick. */
    public function updatedType(): void
    {
        if ($this->type !== NumberRequest::TYPE_RENTAL && $this->service === NumberRequest::SERVICE_ANY) {
            $this->service = 'whatsapp';
        }
    }

    /** Open a product modal from the bento (verify | rent | line). */
    #[On('open-numbers-modal')]
    public function openModal(string $name): void
    {
        if (! in_array($name, ['verify', 'rent', 'line'], true)) {
            return;
        }
        $this->reset('orderId', 'error', 'couponNote');
        $this->modal = $name;
        // Default the request type + a sensible service per line.
        $this->type = match ($name) {
            'rent' => NumberRequest::TYPE_RENTAL,
            'line' => NumberRequest::TYPE_PERMANENT,
            default => NumberRequest::TYPE_OTP,
        };
    }

    public function closeModal(): void
    {
        $this->modal = '';
    }

    /** Open the shared service picker for the number flow. */
    public function pickService(): void
    {
        $this->dispatch('open-service-picker', for: 'numbers', title: 'Choose a service');
    }

    /** Open the shared country picker (numbers source → dial codes). */
    public function pickCountry(): void
    {
        $this->dispatch('open-country-picker', source: 'numbers', args: [], for: 'numbers', title: 'Choose a country');
    }

    /** Step-3 operator comparison: chosen network ('' = auto/best) + panel tab. */
    public string $operator = '';

    public string $opTab = 'prices';

    /** Long-rental duration (US/Getatext only) + auto-renew. */
    public string $rentalDuration = '1w';

    public bool $autoRenew = false;

    /** A US number rental — the only lane where longer durations are real. */
    public function isUsRental(): bool
    {
        return $this->type === NumberRequest::TYPE_RENTAL
            && in_array(strtolower($this->country), ['usa', 'us'], true);
    }

    /** The rentalTime to thread into a request: only for a US long rental. */
    private function activeRentalTime(): ?string
    {
        return $this->isUsRental() && array_key_exists($this->rentalDuration, NumberRequest::RENTAL_DURATIONS)
            ? $this->rentalDuration : null;
    }

    #[On('service-picked')]
    public function onServicePicked(string $slug, string $name, string $for): void
    {
        if ($for !== 'numbers') {
            return;
        }
        $this->service = $slug;
        $this->serviceName = $name;
        $this->operator = ''; // a new service invalidates the operator comparison
    }

    #[On('country-picked')]
    public function onCountryPicked(string $code, string $name, string $for): void
    {
        if ($for !== 'numbers' || $code === '') {
            return;
        }
        $this->country = $code; // numbers source returns provider slugs (usa, nigeria…)
        $this->countryName = $name;
        $this->lineNumbers = []; // a new country invalidates any Naara Line results
        $this->operator = '';    // and the operator comparison
    }

    /** Choose a specific network from the Step-3 comparison ('' = auto/best). */
    public function pickOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    /** Download the operator comparison (retail only — cost never included). */
    public function exportOperatorsCsv(SmsNumberRouter $router)
    {
        $rows = $router->compareOperators($this->country, $this->service, $this->type);
        $filename = "operators-{$this->service}-{$this->country}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Network', 'Price (USD)', 'Availability', 'Success rate (%)']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['label'], number_format($r['retail'], 2), $r['available'], $r['success'] ?? '—']);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ---- Naara Line (permanent number) ---------------------------------------

    /** Optional vanity spec typed into the Line modal (digits + position). */
    public string $vanity = '';

    /** Naara Line search results — [['number','locality','monthly_retail'], …]. */
    public array $lineNumbers = [];

    /** The owning provider for the current results — internal, NEVER shown. */
    public ?string $lineProvider = null;

    public ?string $lineDone = null;

    public function searchLine(PermanentNumberRouter $router): void
    {
        $this->error = null;
        $this->lineDone = null;

        // Interpret the vanity box: bare digits → "ends with"; "*777" / "contains
        // 777" → "contains". Keep it forgiving.
        $raw = strtolower(trim($this->vanity));
        $position = str_contains($raw, 'contain') || str_starts_with($raw, '*') ? 'contains' : 'ends';
        $digits = preg_replace('/\D/', '', $raw);

        try {
            $result = $router->search($this->country, ['digits' => $digits, 'position' => $position, 'limit' => 12]);
        } catch (\Throwable $e) {
            $this->error = 'Number search is unavailable for that country right now. Try another.';
            $this->lineNumbers = [];

            return;
        }

        $this->lineProvider = $result['provider'];       // internal only
        $this->lineNumbers = $result['numbers'] ?? [];
        if ($this->lineNumbers === []) {
            $this->error = 'No matching numbers found. Try a different country or filter.';
        }
    }

    public function getLine(string $number, PermanentNumberRouter $router): void
    {
        $this->error = null;
        $user = auth()->user();

        $key = 'orders:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many requests in a short time. Please wait a minute and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        // Only allow provisioning a number that was actually offered (the provider
        // is validated again inside provision()).
        if ($this->lineProvider === null || ! collect($this->lineNumbers)->contains('number', $number)) {
            $this->error = 'Please search again — that number is no longer listed.';

            return;
        }

        try {
            // provision() owns the whole money path: charge first month, buy at the
            // provider, persist the subscription, refund on any failure.
            $vnum = $router->provision($user, $this->country, $number, $this->lineProvider);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low for the first month. Please top up and try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error', title: 'Payment failed',
                message: 'Your wallet balance is too low — you were not charged. Top up and try again.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        } catch (SmsException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->lineDone = $vnum->phone_number ?? $number;
        $this->lineNumbers = [];
        Mailer::notify($user, new OrderPlacedNotification('number', 'Naara Line', (float) $vnum->monthly_retail, 'USD'));
        $this->dispatch('nx-toast', variant: 'hero', type: 'success', title: 'Naara Line active',
            message: 'Your permanent number is ready — set up call forwarding or the dialer from your dashboard.',
            cta: ['label' => 'View my numbers', 'href' => route('dashboard')]);
    }

    public function order(WalletService $wallet, SmsNumberRouter $router, CouponEngine $coupons, PricingEngine $pricing, MerchantEarningsService $earnings): void
    {
        $this->error = null;
        $this->couponNote = null;
        $user = auth()->user();

        // Order rate limit: 10/min (blueprint Section 19.2).
        $key = 'orders:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->error = 'Too many requests in a short time. Please wait a minute and try again.';

            return;
        }
        RateLimiter::hit($key, 60);

        // Smart Buy (§3): auto-pick the cheapest in-stock country for the service,
        // ignoring any manual operator choice (that belongs to a manual country).
        $operator = $this->operator ?: null;
        if ($this->buyMode === 'smart' && $this->type === NumberRequest::TYPE_OTP) {
            $best = $router->cheapestCountryFor($this->service);
            if ($best !== null) {
                $this->country = $best;
                $this->countryName = CountryNames::name($best) ?: ucfirst($best);
            }
            $operator = null; // best country implies best operator
        }

        $rentalTime = $this->activeRentalTime();
        try {
            $quote = $router->quote(new NumberRequest($this->country, $this->type, $this->service, $user, operator: $operator, rentalTime: $rentalTime, autoRenew: $this->autoRenew));
        } catch (SmsException $e) {
            $this->error = 'No number available for that country and service right now. Try another.';

            return;
        }

        // Merchant lane (ROADMAP §Layer 3.2/3.4): a reseller's customer pays the
        // merchant price (retail + admin-set reseller margin); the M−R upcharge is
        // accrued to that merchant after the number is reserved. plainRetail is
        // the accrual floor so the admin's own margin is never given away.
        $merchant = MerchantBranding::forCustomer($user);
        $plainRetail = (float) $quote['retail'];
        $retail = $merchant !== null
            ? $pricing->merchantSmsPrice((float) $quote['cost'], $quote['provider'], $merchant)
            : $plainRetail;
        $listRetail = $retail;

        // Coupon (Module 31): re-validated here, priced off the LIVE quote and
        // clamped by CouponEngine so the charge never dips below cost + profit.
        $couponModel = null;
        $couponClamped = false;
        if (trim($this->coupon) !== '') {
            $couponModel = $coupons->usable($this->coupon, $user, 'number');
            if (! $couponModel) {
                $this->error = 'That coupon code is not valid for this purchase. Remove it or try another.';

                return;
            }
            $priced = $coupons->price($couponModel, $retail, (float) $quote['cost'], 'number');
            $retail = $priced['price'];
            $couponClamped = $priced['clamped'];
        }

        $ref = "number-checkout:{$user->id}:".now()->timestamp;

        try {
            $debit = $wallet->debit($user, $retail, 'USD', ['reference' => $ref, 'description' => "Number: {$this->service}"]);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low. Please top up and try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Payment failed',
                message: 'Your wallet balance is too low — you were not charged. Top up and try again.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

            return;
        }

        // Double-submit guard (money-safety rule 7): the debit is idempotent on
        // $ref but reserving a number is not. A same-second re-submit returns the
        // EXISTING debit (wasRecentlyCreated === false) — a number was already
        // reserved for this charge, so reserving again would rent a second number
        // at our cost against a single charge. Stop here.
        if (! $debit->wasRecentlyCreated) {
            $this->error = 'This request is already being processed — your number will appear on your dashboard shortly.';

            return;
        }

        try {
            $result = $router->order(new NumberRequest(
                $this->country, $this->type, $this->service, $user, 'USD', $retail, $operator, $rentalTime, $this->autoRenew
            ));
        } catch (SmsException $e) {
            // The router already refunded (charged was set).
            $this->error = 'Could not reserve a number — your wallet was refunded.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Could not reserve a number',
                message: 'No number was available for that country and service. Your wallet was refunded in full — you were not charged.');

            return;
        }

        // Coupon is burned only once the number is actually reserved.
        if ($couponModel) {
            $coupons->redeem($couponModel, $user, 'number', $ref, $listRetail, $retail, $couponClamped);
            $this->couponNote = 'Coupon applied — you saved $'.number_format($listRetail - $retail, 2).'.';
        }

        // Merchant earnings (ROADMAP §Layer 3.4): accrue the upcharge collected
        // above plain retail to the customer's merchant. Idempotent on $ref.
        if ($merchant !== null) {
            $earnings->accrue($merchant, $user, 'number', $plainRetail, $retail, 'earn:'.$ref);
        }

        // Order-confirmation email (best-effort; never blocks the money path).
        Mailer::notify($user, new OrderPlacedNotification('number', ucfirst($this->service), $retail, 'USD'));

        // WhatsApp Autopilot (§7): mirror to WhatsApp for opted-in users (gated,
        // best-effort — queues a template or silently no-ops).
        app(WhatsAppAutopilot::class)
            ->notify($user, 'number_delivered', [$user->name ?: 'there', ucfirst($this->service)]);

        // First-purchase NaaraCredits bonus (loyalty; idempotent, best-effort).
        app(CreditService::class)->grantOnce(
            $user,
            (float) CreditSettings::get('first_purchase_bonus', 0),
            'first_purchase',
            'First purchase bonus',
        );

        PollSmsOtpJob::dispatch($result->order->id, 'USD');
        $this->orderId = $result->order->id;

        // Hero toast — dispatched only after the number is reserved (server-anchored).
        $this->dispatch('nx-toast', variant: 'hero', type: 'success',
            title: 'Number reserved',
            message: 'We’re fetching your code now — it’ll appear here in a moment.');
    }

    public function reset_(): void
    {
        $this->reset('orderId', 'error', 'coupon', 'couponNote');
    }

    public function render()
    {
        // orderId is a public (attacker-settable) property, so the lookup MUST be
        // scoped to the owner — the view renders the phone number and OTP code,
        // and an unscoped find() would let anyone read another user's SMS code.
        $order = $this->orderId
            ? SmsOrder::where('user_id', auth()->id())->find($this->orderId)
            : null;

        // The FULL catalogue (static base + synced provider lists) — never a
        // curated handful. Provided at render time so the Livewire snapshot
        // isn't bloated with the whole list.
        // Best-effort live retail for the OPEN modal (never blocks; providers may
        // be Coming Soon). Only computed while a buy modal is open, so the whole
        // catalogue render stays cheap. Never exposes cost — retail only.
        $modalPrice = null;
        $operators = [];
        if (in_array($this->modal, ['verify', 'rent'], true) && $order === null) {
            $router = app(SmsNumberRouter::class);
            try {
                $q = $router->quote(
                    new NumberRequest($this->country, $this->type, $this->service, auth()->user(), operator: $this->operator ?: null, rentalTime: $this->activeRentalTime(), autoRenew: $this->autoRenew)
                );
                $modalPrice = (float) $q['retail'];
            } catch (\Throwable) {
                $modalPrice = null; // out of stock / not configured — shown as "checked at reservation"
            }

            // Step-3 operator comparison for Verify (manual mode) — retail only.
            if ($this->modal === 'verify' && $this->buyMode === 'manual') {
                try {
                    $operators = $router->compareOperators($this->country, $this->service, $this->type);
                } catch (\Throwable) {
                    $operators = [];
                }
            }
        }

        return view('livewire.get-number', [
            'order' => $order,
            'countries' => NumberCatalogue::countries(),
            'services' => NumberCatalogue::services(),
            // "Any service" (full rent) is only offered when a full-rent-capable
            // provider is configured, so there's never a dead option.
            'fullRentAvailable' => ProviderStatus::isActive('herosms')
                || ProviderStatus::isActive('virtsms'),
            'modalPrice' => $modalPrice,
            'operators' => $operators,
            // US rentals (Getatext) can pick a longer duration; elsewhere it's a
            // short-term rental at the provider's fixed period.
            'isUsRental' => $this->isUsRental(),
            'rentalDurations' => NumberRequest::RENTAL_DURATIONS,
            'permanentAvailable' => ProviderStatus::isActive('twilio')
                || ProviderStatus::isActive('telnyx'),
        ]);
    }
}
