<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
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
}
