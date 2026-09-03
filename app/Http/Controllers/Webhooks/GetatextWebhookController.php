<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\OtpReceived;
use App\Http\Controllers\Controller;
use App\Models\SmsOrder;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Getatext OTP webhook (blueprint Section 8.2). Getatext POSTs the received
 * code; we match it to the pending sms_order by provider ref, store the code,
 * mark it completed, and broadcast OtpReceived.
 *
 * Getatext sends from many IPs, so we do NOT IP-whitelist. Getatext has no
 * HMAC signature; if a GETATEXT_WEBHOOK_TOKEN shared secret is configured we
 * verify it (constant-time). Every webhook is logged before processing and
 * handling is idempotent (a repeat delivery never double-processes).
 */
class GetatextWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Optional shared-secret verification (constant-time).
        $verified = true;
        $secret = config('services.getatext.webhook_token');
        if (! empty($secret)) {
            $provided = $request->header('X-Webhook-Token') ?? $request->query('token');
            $verified = is_string($provided) && hash_equals((string) $secret, $provided);
        }

        $log = WebhookLog::create([
            'provider' => 'getatext',
            'event_type' => $payload['status'] ?? null,
            'payload' => $payload,
            'signature' => $request->header('X-Webhook-Token'),
            'verified' => $verified,
            'processed' => false,
        ]);

        if (! $verified) {
            return response()->json(['ok' => false, 'error' => 'unverified'], 401);
        }

        $ref = isset($payload['id']) ? (string) $payload['id'] : null;
        $code = $payload['code'] ?? null;
        if ($ref === null || $code === null) {
            return response()->json(['ok' => false, 'error' => 'missing id or code'], 422);
        }

        $order = SmsOrder::query()
            ->where('provider', 'getatext')
            ->where('getatext_id', $ref)
            ->first();

        // Unknown order — acknowledge so Getatext stops retrying.
        if ($order === null) {
            $log->update(['processed' => true, 'processed_at' => now(), 'error' => 'no matching order']);

            return response()->json(['ok' => true]);
        }

        // Idempotent: only act on the first delivery.
        if ($order->status !== 'completed') {
            $order->update([
                'otp_code' => (string) $code,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            OtpReceived::dispatch($order->user_id, $order->id, (string) $code, (string) $order->phone_number);
        }

        $log->update(['processed' => true, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
