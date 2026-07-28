<?php

namespace App\Http\Controllers;

use App\Models\GiftCardOrder;
use App\Support\FeatureFlags;
use Illuminate\Http\Request;

/**
 * Naara Gift order history + the three-state redemption screen (code / link /
 * account), owner-scoped. Feature-gated with the storefront.
 */
class GiftCardOrderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);

        $orders = GiftCardOrder::where('user_id', $request->user()->id)
            ->latest()->paginate(15);

        return view('gift-cards.orders', compact('orders'));
    }

    public function show(Request $request, GiftCardOrder $order)
    {
        abort_unless(FeatureFlags::enabled('naara_gift'), 404);
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('gift-cards.receipt', ['order' => $order]);
    }
}
