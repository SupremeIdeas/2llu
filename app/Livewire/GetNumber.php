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

    public function order(WalletService $wallet, SmsNumberRouter $router, CouponEngine $coupons): void
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

        $retail = $quote['retail'];
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
            $wallet->debit($user, $retail, 'USD', ['reference' => $ref, 'description' => "Number: {$this->service}"]);
        } catch (InsufficientBalanceException $e) {
            $this->error = 'Your wallet balance is too low. Please top up and try again.';
            $this->dispatch('nx-toast', variant: 'hero', type: 'error',
                title: 'Payment failed',
                message: 'Your wallet balance is too low — you were not charged. Top up and try again.',
                cta: ['label' => 'Top up wallet', 'href' => route('wallet')]);

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
        $order = $this->orderId ? SmsOrder::find($this->orderId) : null;

        // The FULL catalogue (static base + synced provider lists) — never a
        // curated handful. Provided at render time so the Livewire snapshot
        // isn't bloated with the whole list.
        return view('livewire.get-number', [
            'order' => $order,
            'countries' => \App\Support\NumberCatalogue::countries(),
            'services' => \App\Support\NumberCatalogue::services(),
        ]);
    }
}
