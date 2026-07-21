<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\CreditWalletJob;
use App\Models\WebhookLog;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Payment webhooks for Flutterwave / Paystack / Stripe (blueprint Section
 * 19.3). Verify the signature BEFORE touching the payload, log it, return 200
 * after verify, and process the credit in a queued job idempotent on the
 * provider reference. A failed verification is logged and rejected 401.
 */
class PaymentWebhookController extends Controller
{
    private const GATEWAYS = ['flutterwave', 'paystack', 'stripe', 'paypal', 'binance', 'nowpayments', 'cryptomus', 'coinpayments', 'payssion'];

    public function __invoke(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, self::GATEWAYS, true)) {
            throw new NotFoundHttpException;
        }

        /** @var PaymentGatewayInterface $svc */
        $svc = app("pay.{$gateway}");
        $verified = $svc->verifySignature($request);

        $log = WebhookLog::create([
            'provider' => $gateway,
            'event_type' => $request->input('event') ?? $request->input('type'),
            'payload' => $request->all(),
            'signature' => $request->header('Stripe-Signature')
                ?? $request->header('x-paystack-signature')
                ?? $request->header('verif-hash')
                ?? $request->header('paypal-transmission-sig')
                ?? $request->header('BinancePay-Signature')
                ?? $request->header('x-nowpayments-sig')
                ?? $request->header('HMAC')
                ?? $request->input('sign')          // cryptomus (in-body)
                ?? $request->input('notify_sig'),   // payssion (in-body)
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $event = $svc->parseWebhook($request);
        if ($event !== null && $event->isSuccessful() && $event->userId !== null) {
            CreditWalletJob::dispatch(
                $event->gateway,
                $event->reference,
                $event->userId,
                $event->amount,
                $event->currency,
            );
        }

        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
