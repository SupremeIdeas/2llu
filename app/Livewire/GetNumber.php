<?php

namespace App\Livewire;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\SmsException;
use App\Jobs\PollSmsOtpJob;
use App\Models\SmsOrder;
use App\Services\Pricing\CouponEngine;
use App\Services\SMS\NumberRequest;
use App\Services\SMS\SmsNumberRouter;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Get-a-Number flow (blueprint Section 12.1). Country + service + type, routed
 * by the capability router. Debits the live retail, orders through the lane,
 * then polls for the code. The user never sees a provider name or a cost.
 */
#[Layout('components.layouts.customer')]
class GetNumber extends Component
{
    public string $country = 'usa';

    public string $service = 'whatsapp';

    public string $type = 'otp';

    public ?int $orderId = null;

    public ?string $error = null;

    /** Coupon (Module 31) — applied to the live retail quote, floor-clamped. */
    public string $coupon = '';

    public ?string $couponNote = null;

    /** Client-side filter for the (large) service picker. */
    public string $serviceSearch = '';

    /** Switching away from rental clears an "any service" (full-rent) pick. */
    public function updatedType(): void
    {
        if ($this->type !== NumberRequest::TYPE_RENTAL && $this->service === NumberRequest::SERVICE_ANY) {
            $this->service = 'whatsapp';
        }
    }

    public function order(WalletService $wallet, SmsNumberRouter $router, CouponEngine $coupons, \App\Services\Pricing\PricingEngine $pricing, \App\Services\Merchants\MerchantEarningsService $earnings): void
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

        try {
            $quote = $router->quote(new NumberRequest($this->country, $this->type, $this->service, $user));
        } catch (SmsException $e) {
            $this->error = 'No number available for that country and service right now. Try another.';

            return;
        }

        // Merchant lane (ROADMAP §Layer 3.2/3.4): a reseller's customer pays the
        // merchant price (retail + admin-set reseller margin); the M−R upcharge is
        // accrued to that merchant after the number is reserved. plainRetail is
        // the accrual floor so the admin's own margin is never given away.
        $merchant = \App\Support\MerchantBranding::forCustomer($user);
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
                $this->country, $this->type, $this->service, $user, 'USD', $retail
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
        \App\Support\Mailer::notify($user, new \App\Notifications\OrderPlacedNotification('number', ucfirst($this->service), $retail, 'USD'));

        // First-purchase NaaraCredits bonus (loyalty; idempotent, best-effort).
        app(\App\Services\Credits\CreditService::class)->grantOnce(
            $user,
            (float) \App\Support\CreditSettings::get('first_purchase_bonus', 0),
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
        return view('livewire.get-number', [
            'order' => $order,
            'countries' => \App\Support\NumberCatalogue::countries(),
            'services' => \App\Support\NumberCatalogue::services(),
            // "Any service" (full rent) is only offered when a full-rent-capable
            // provider is configured, so there's never a dead option.
            'fullRentAvailable' => \App\Support\ProviderStatus::isActive('smsactivate'),
        ]);
    }
}
