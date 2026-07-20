<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\EsimProviderException;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Jobs\AlertAdminJob;
use App\Models\ApiOrder;
use App\Models\EsimOrder;
use App\Models\EsimPlan;
use App\Services\Api\ApiWalletService;
use App\Services\eSIM\ProviderRouter;
use App\Services\Pricing\PricingEngine;
use App\Support\Niche\LpaActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Developer API — orders (ROADMAP §Layer 2). Bills the client's PREPAID API
 * wallet (never a user wallet) at the developer price, fulfils via the shared
 * provider router, and returns a masked order (no cost, no supplier). Money-safe
 * like the storefront: charge-first, idempotent per (client, reference), refund
 * on provider failure, and an orphan-charge guard if persistence fails.
 */
class OrderController extends Controller
{
    public function store(Request $request, PricingEngine $pricing, ProviderRouter $router, ApiWalletService $wallet): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:esim',                 // numbers land in a later increment
            'plan_id' => 'required|integer',
            'reference' => 'nullable|string|max:64',       // developer idempotency key
        ]);

        $client = $request->user();
        $ref = ($data['reference'] ?? null) ?: (string) Str::uuid();

        // Idempotency at the order level: a retried reference never provisions or
        // charges twice — return the order we already made.
        $existing = ApiOrder::where('api_client_id', $client->id)->where('reference', $ref)->first();
        if ($existing) {
            return response()->json($existing->toApiArray(), 200);
        }

        $plan = EsimPlan::where('is_active', true)->find($data['plan_id']);
        if (! $plan) {
            return response()->json(['error' => 'invalid_plan', 'message' => 'Unknown or inactive plan.'], 422);
        }

        $price = round($pricing->developerEsimPrice($plan), 4);

        // 1) Charge the prepaid API wallet first (never fulfil without payment).
        try {
            $wallet->debit($client, $price, ['reference' => "api-order:{$ref}", 'description' => "eSIM: {$plan->name}"]);
        } catch (InsufficientBalanceException) {
            return response()->json([
                'error' => 'insufficient_balance',
                'message' => 'Top up your API balance and retry.',
            ], 402);
        }

        // 2) Fulfil at the provider (shared loop; touches no user wallet).
        try {
            $result = $router->fulfil($plan, $price, $client->owner);
        } catch (EsimProviderException) {
            $wallet->refund($client, $price, ['reference' => "refund:api-order:{$ref}", 'description' => 'Order not fulfilled']);

            return response()->json([
                'error' => 'unfulfilled',
                'message' => 'No provider could fulfil this plan right now. Your API balance was refunded.',
            ], 502);
        }

        // 3) Persist. Orphan-charge guard: refund + alert if we can't save.
        try {
            $esim = EsimOrder::create([
                'user_id' => $client->owner_user_id,
                'plan_id' => $plan->id,
                'provider' => $result->provider,
                'provider_order_ref' => data_get($result->payload, 'orderReference') ?? data_get($result->payload, 'id'),
                'iccid' => data_get($result->payload, 'iccid') ?? data_get($result->payload, 'esims.0.iccid'),
                'qr_code_url' => data_get($result->payload, 'qr_code') ?? data_get($result->payload, 'qrCodeUrl'),
                'lpa_string' => LpaActivation::fromPayload($result->payload),
                'status' => 'processing',
                'price_charged' => $price,       // real money collected (developer price)
                'wholesale_cost' => $result->cost, // internal only (EsimOrder hides it)
                'currency' => 'USD',
            ]);

            $order = ApiOrder::create([
                'api_client_id' => $client->id,
                'kind' => 'esim',
                'reference' => $ref,
                'status' => 'processing',
                'price_usd' => $price,
                'currency' => 'USD',
                'esim_order_id' => $esim->id,
                'result' => [ // masked delivery only — never cost/provider
                    'iccid' => $esim->iccid,
                    'qr_code' => $esim->qr_code_url,
                    'lpa' => $esim->lpa_string,
                ],
            ]);
        } catch (Throwable $e) {
            $wallet->refund($client, $price, ['reference' => "refund:api-order:{$ref}", 'description' => 'Order could not be saved']);
            AlertAdminJob::dispatch(
                code: 'api_order_save_failed',
                message: "API order for client {$client->id} fulfilled but failed to persist; API balance refunded.",
                context: ['api_client_id' => $client->id, 'plan_id' => $plan->id, 'reference' => $ref],
            );

            return response()->json([
                'error' => 'order_failed',
                'message' => 'Something went wrong finalising the order. Your API balance was refunded.',
            ], 500);
        }

        return response()->json($order->toApiArray(), 201);
    }

    /** Status of a previously placed order (scoped to the calling client). */
    public function show(Request $request, string $reference): JsonResponse
    {
        $order = ApiOrder::where('api_client_id', $request->user()->id)
            ->where('reference', $reference)
            ->first();

        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Reflect the latest fulfilment status from the linked eSIM order.
        if ($order->esim_order_id && ($esim = $order->esimStatus())) {
            $order->status = $esim;
        }

        return response()->json($order->toApiArray());
    }
}
