<?php

namespace App\Services\GiftCards;

use App\Exceptions\InsufficientBalanceException;
use App\Jobs\AlertAdminJob;
use App\Models\GiftCardOrder;
use App\Models\GiftCardProduct;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Support\Auditor;
use App\Support\GiftCardFraud;
use App\Support\GiftCardPricing;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Naara Gift purchase money path (Phase 3). Same discipline as eSIM/merchant
 * checkout: retail quoted through PricingEngine, fraud gate, ATOMIC wallet debit
 * (idempotent on the transaction ref), the order row created BEFORE the provider
 * call (no orphan charge), refund-on-provider-failure, and a review-queue hold
 * for high-value orders. Provider identity + cost never leak to the buyer.
 */
class GiftCardOrderService
{
    /** @var array<string, class-string<GiftCardProviderInterface>> */
    private const PROVIDERS = [
        'reloadly' => ReloadlyGiftCardService::class,
        'zendit' => ZenditVoucherService::class,
    ];

    public function __construct(
        private WalletService $wallet,
        private GiftCardPricing $pricing,
    ) {}

    private function provider(string $key): GiftCardProviderInterface
    {
        if (app()->bound('giftcard.'.$key)) {
            return app('giftcard.'.$key);
        }

        return app(self::PROVIDERS[$key] ?? throw new GiftCardException('Unknown provider.'));
    }

    /**
     * Buy a gift card for $amount (face value) with the required $fields.
     *
     * @param  array<string, string>  $fields
     *
     * @throws GiftCardException
     */
    public function purchase(User $user, GiftCardProduct $product, float $amount, array $fields): GiftCardOrder
    {
        $amount = round($amount, 2);
        $this->assertAmount($product, $amount);
        $this->assertFields($product, $fields);

        // Authoritative retail quote (this one logs).
        $retail = round($this->pricing->retail($product, $amount, log: true), 4);

        // Fraud gate (velocity + cooling-off) BEFORE any money moves.
        GiftCardFraud::assert($user, $retail);

        $ref = 'giftcard:'.$user->id.':'.$product->id.':'.Str::uuid();

        // Atomic, idempotent debit.
        try {
            $debit = $this->wallet->debit($user, $retail, 'USD', [
                'reference' => $ref,
                'description' => "Gift card: {$product->brand_name} {$product->currency} ".number_format($amount, 2),
            ]);
        } catch (InsufficientBalanceException) {
            throw new GiftCardException('Your wallet is too low — top up at least $'.number_format($retail, 2).'.');
        }
        if (! $debit->wasRecentlyCreated) {
            throw new GiftCardException('That purchase is already being processed.');
        }

        // Record the order BEFORE calling the provider (no orphan charge).
        $order = GiftCardOrder::create([
            'user_id' => $user->id,
            'gift_card_product_id' => $product->id,
            'provider' => $product->provider,
            'provider_product_id' => $product->provider_product_id,
            'brand_name' => $product->brand_name,
            'face_value' => $amount,
            'currency' => $product->currency ?: 'USD',
            'price_charged' => $retail,
            'status' => GiftCardOrder::STATUS_PROCESSING,
            'transaction_ref' => $ref,
            'fields' => $fields,
        ]);

        // High-value orders hold in review (funds already committed) — fulfilled
        // on admin approval, refunded on rejection.
        if (GiftCardFraud::needsReview($retail)) {
            $order->update(['status' => GiftCardOrder::STATUS_REVIEW, 'review_reason' => 'Above the review threshold.']);
            Auditor::log('giftcard.review_held', GiftCardOrder::class, $order->id, ['amount' => $retail]);

            return $order;
        }

        return $this->fulfil($order, $product);
    }

    /** Call the provider, store the receipt, refund on failure. */
    private function fulfil(GiftCardOrder $order, GiftCardProduct $product): GiftCardOrder
    {
        try {
            $result = $this->provider($product->provider)->order(
                $product->provider_product_id,
                (float) $order->face_value,
                $order->currency,
                (array) $order->fields,
                $order->transaction_ref,
            );
        } catch (GiftCardProviderException $e) {
            $this->wallet->refund($order->user, (float) $order->price_charged, 'USD', [
                'reference' => 'refund:'.$order->transaction_ref,
                'description' => 'Gift card could not be delivered',
            ]);
            $order->update(['status' => GiftCardOrder::STATUS_FAILED]);
            Auditor::log('giftcard.failed_refunded', GiftCardOrder::class, $order->id);

            throw new GiftCardException('That gift card could not be delivered right now — your wallet was refunded.');
        }

        try {
            $order->update([
                'provider_tx_id' => $result['provider_tx_id'] ?? null,
                'receipt' => $result['receipt'] ?? [],
                'status' => $result['status'] === 'delivered' ? GiftCardOrder::STATUS_DELIVERED : GiftCardOrder::STATUS_PROCESSING,
            ]);
        } catch (Throwable $e) {
            // The card was ordered but we couldn't record the receipt — never
            // refund a delivered card; alert an admin to reconcile by hand.
            AlertAdminJob::dispatch(
                code: 'giftcard_receipt_save_failed',
                message: "Gift card order {$order->id} delivered but the receipt could not be saved: {$e->getMessage()}",
                context: ['order_id' => $order->id, 'ref' => $order->transaction_ref],
            );
        }

        Auditor::log('giftcard.purchased', GiftCardOrder::class, $order->id, ['status' => $order->status]);

        return $order->fresh();
    }

    /** Admin approves a held order → fulfil it. */
    public function approveReview(GiftCardOrder $order): GiftCardOrder
    {
        abort_unless($order->status === GiftCardOrder::STATUS_REVIEW, 422);
        $product = $order->product ?? GiftCardProduct::where('provider', $order->provider)
            ->where('provider_product_id', $order->provider_product_id)->firstOrFail();
        $order->update(['status' => GiftCardOrder::STATUS_PROCESSING]);

        return $this->fulfil($order, $product);
    }

    /** Admin rejects a held order → refund. */
    public function rejectReview(GiftCardOrder $order, ?string $reason = null): void
    {
        abort_unless($order->status === GiftCardOrder::STATUS_REVIEW, 422);
        $this->wallet->refund($order->user, (float) $order->price_charged, 'USD', [
            'reference' => 'refund:'.$order->transaction_ref,
            'description' => 'Gift card order declined in review',
        ]);
        $order->update(['status' => GiftCardOrder::STATUS_REFUNDED, 'review_reason' => $reason ?: $order->review_reason]);
        Auditor::log('giftcard.review_rejected', GiftCardOrder::class, $order->id);
    }

    private function assertAmount(GiftCardProduct $product, float $amount): void
    {
        if ($amount <= 0) {
            throw new GiftCardException('Choose an amount.');
        }
        if ($product->isRange()) {
            if ($amount < (float) $product->min_amount || $amount > (float) $product->max_amount) {
                throw new GiftCardException('Enter an amount between '.$product->min_amount.' and '.$product->max_amount.'.');
            }
        } elseif (! collect((array) $product->fixed_denominations)->map(fn ($v) => (float) $v)->contains($amount)) {
            throw new GiftCardException('Choose one of the available amounts.');
        }
    }

    private function assertFields(GiftCardProduct $product, array $fields): void
    {
        foreach ((array) $product->required_fields as $f) {
            $key = $f['key'] ?? null;
            if ($key && ($f['required'] ?? true) && trim((string) ($fields[$key] ?? '')) === '') {
                throw new GiftCardException('Please fill in: '.($f['label'] ?? $key).'.');
            }
        }
    }
}
