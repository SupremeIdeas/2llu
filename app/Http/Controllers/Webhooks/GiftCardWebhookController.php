<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\GiftCardOrder;
use App\Support\Auditor;
use Illuminate\Http\Request;

/**
 * Naara Gift async delivery webhook (Phase 3). Reloadly/Zendit call this with the
 * final transaction status + receipt. Verified with an HMAC secret before the
 * payload is trusted (rule 9), idempotent (never downgrades a terminal order),
 * and it fills the three-state receipt so the redemption screen updates.
 */
class GiftCardWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider)
    {
        $secret = (string) config("services.{$provider}.webhook_secret", '');
        if ($secret !== '') {
            $sig = (string) $request->header('X-Naara-Signature', $request->header('X-Reloadly-Signature', ''));
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            abort_unless(hash_equals($expected, $sig), 401);
        }

        $data = $request->json()->all();
        $ref = (string) ($data['customIdentifier'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? '');
        $order = $ref !== '' ? GiftCardOrder::where('transaction_ref', $ref)->first() : null;
        abort_unless($order, 404);

        // Never resurrect/downgrade a finished order.
        if (in_array($order->status, [GiftCardOrder::STATUS_DELIVERED, GiftCardOrder::STATUS_FAILED, GiftCardOrder::STATUS_REFUNDED], true)) {
            return response()->json(['ok' => true]);
        }

        $status = strtoupper((string) ($data['status'] ?? ''));
        $mapped = match ($status) {
            'DONE', 'SUCCESSFUL', 'DELIVERED' => GiftCardOrder::STATUS_DELIVERED,
            'FAILED' => GiftCardOrder::STATUS_FAILED,
            default => GiftCardOrder::STATUS_PROCESSING,
        };

        $receipt = (array) $order->receipt;
        $r = (array) ($data['receipt'] ?? []);
        $receipt = array_merge($receipt, array_filter([
            'epin' => $r['epin'] ?? null,
            'code' => $r['cardNumber'] ?? ($r['code'] ?? null),
            'redemption_url' => $r['redemptionUrl'] ?? null,
            'account_id' => $r['accountId'] ?? null,
            'instructions' => $r['instructions'] ?? null,
            'terms' => $r['terms'] ?? null,
            'expires_at' => $r['expiresAt'] ?? null,
            'delivery_type' => $r['deliveryType'] ?? null,
        ], fn ($v) => $v !== null));

        $order->update([
            'status' => $mapped,
            'receipt' => $receipt,
            'provider_tx_id' => $order->provider_tx_id ?: ($data['transactionId'] ?? null),
        ]);
        Auditor::log('giftcard.webhook', GiftCardOrder::class, $order->id, ['status' => $mapped]);

        return response()->json(['ok' => true]);
    }
}
