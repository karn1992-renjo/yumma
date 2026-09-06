<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Mirrors the customer app's local cart state for one restaurant so
     * abandoned-cart detection (App\Services\CartRecoveryService) has real
     * data to work with. Called by the Flutter customer app's CartProvider
     * on every cart mutation (add/remove/update/clear), debounced client-side.
     * Sending an empty items array clears/deletes the tracked cart -- it is
     * never stored as an empty row (an empty cart is not "abandoned").
     */
    public function sync(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'items' => ['present', 'array'],
            'items.*.menu_item_id' => ['required_with:items', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:191'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customerId = auth()->id();

        if (empty($validated['items'])) {
            Cart::where('customer_id', $customerId)
                ->where('restaurant_id', $validated['restaurant_id'])
                ->delete();

            return response()->json(['success' => true, 'cleared' => true]);
        }

        $cart = Cart::updateOrCreate(
            ['customer_id' => $customerId, 'restaurant_id' => $validated['restaurant_id']],
            [
                'items' => $validated['items'],
                'subtotal' => $validated['subtotal'] ?? 0,
                'last_activity_at' => now(),
                'notified_at' => null,
            ]
        );

        return response()->json(['success' => true, 'cart_id' => $cart->id]);
    }
}
