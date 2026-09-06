<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCancellationLimit;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->middleware('auth');
        $this->refundService = $refundService;
    }

    public function index()
    {
        $orders = Order::with(['restaurant'])
            ->where('customer_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('customer.orders.index', compact('orders'));
    }

    public function show($id)
    {
        $order = Order::with(['restaurant', 'orderItems'])
            ->where('customer_id', Auth::id())
            ->findOrFail($id);

        return view('customer.orders.show', compact('order'));
    }

    public function track($id)
    {
        $order = Order::with(['restaurant', 'driver'])
            ->where('customer_id', Auth::id())
            ->findOrFail($id);

        return view('customer.orders.track', compact('order'));
    }

    public function reorder(Request $request, $id)
    {
        $order = Order::with('restaurant')
            ->where('customer_id', Auth::id())
            ->findOrFail($id);

        if (!in_array($order->status, ['delivered', 'completed'])) {
            return redirect()->route('customer.orders.index')
                ->with('error', 'Only completed or delivered orders can be reordered.');
        }

        $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
        $cart = [];

        foreach ($items as $item) {
            $cart[] = [
                'id' => $item['menu_item_id'] ?? $item['id'] ?? null,
                'name' => $item['item_name'] ?? $item['name'] ?? 'Item',
                'price' => $item['unit_price'] ?? $item['price'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'restaurant_id' => $order->restaurant_id,
            ];
        }

        session([
            'checkout_cart' => $cart,
            'checkout_restaurant_id' => $order->restaurant_id,
            'checkout_subtotal' => collect($cart)->sum(fn ($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 0)),
        ]);

        return redirect()->route('checkout.index');
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::where('customer_id', Auth::id())->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $canInstantCancel = $order->isCancellable();
        $canForceCancel = in_array($order->status, ['pending', 'confirmed', 'preparing', 'ready_for_pickup'], true);

        if (! OrderCancellationLimit::isWithinWindow($order, 'customer', 15)) {
            $minutes = OrderCancellationLimit::windowMinutesFor('customer', 15);

            return redirect()->back()->with('error', "Cancellation window expired. Orders can only be cancelled within {$minutes} minutes of placement.");
        }

        if (! $canInstantCancel && ! $canForceCancel) {
            return redirect()->back()->with('error', 'This order can no longer be cancelled. Please contact support for further help.');
        }

        DB::beginTransaction();

        try {
            if (($canForceCancel || $canInstantCancel) && $order->payment_status === 'success') {
                $refundResult = $this->refundService->processRefund($order, $request->reason);

                if (! $refundResult['success']) {
                    throw new \Exception('Refund processing failed: ' . $refundResult['message']);
                }

                $message = 'Order cancelled. Refund has been initiated as per the active refund policy.';
            } elseif ($canForceCancel) {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->reason,
                    'refund_status' => 'pending',
                    'refund_reason' => $request->reason,
                ]);
                $message = 'Order cancelled. Refund, if applicable, will be handled as per the active refund policy.';
            } else {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->reason,
                ]);
                $message = 'Order cancelled successfully!';
            }

            DB::commit();

            return redirect()->route('customer.orders.show', $order->id)->with('success', $message);
        } catch (\Exception $e) {
            DB::rollback();

            return redirect()->back()->with('error', 'Failed to cancel order: ' . $e->getMessage());
        }
    }
}
