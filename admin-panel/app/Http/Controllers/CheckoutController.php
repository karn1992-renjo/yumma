<?php

namespace App\Http\Controllers;

use App\Events\NewOrderEvent;
use App\Helpers\FirebaseHelper;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\RestaurantStaff;
use App\Models\PromoCode;
use App\Models\Address;
use App\Models\DeliveryChargeSetting;
use App\Models\MenuItem;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PaymentGatewayService;
use App\Services\OrderReleaseService;
use App\Services\PrinterService;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        // Get cart from session or localStorage
        $cart = session()->get('checkout_cart', []);
        
        if (empty($cart)) {
            // Try to get from request
            if ($request->has('cart')) {
                $cart = json_decode($request->cart, true);
            } else {
                // Redirect to home with error
                return redirect()->route('home')->with('error', 'Your cart is empty. Please add items to checkout.');
            }
        }
        
        // Get restaurant from cart
        $restaurantId = $cart[0]['restaurant_id'] ?? null;
        $restaurant = $restaurantId ? Restaurant::find($restaurantId) : null;
        
        if (!$restaurant) {
            return redirect()->route('home')->with('error', 'Restaurant not found');
        }
        
        // Calculate cart totals
        $subtotal = 0;
        $cartItems = [];
        foreach ($cart as $item) {
            $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            $subtotal += $itemTotal;
            $cartItems[] = [
                'id' => $item['id'],
                'name' => $item['name'] ?? 'Item',
                'price' => $item['price'] ?? 0,
                'quantity' => $item['quantity'] ?? 1,
                'selected_variant' => $item['selected_variant'] ?? null,
                'selected_add_ons' => $item['selected_add_ons'] ?? [],
                'total' => $itemTotal
            ];
        }
        
        $addresses = Auth::user()->addresses()->get();
        $selectedAddress = $addresses->firstWhere('is_default', true) ?: $addresses->first();
        $pricing = $this->buildPricingSummary(
            $restaurant,
            $subtotal,
            $selectedAddress?->latitude,
            $selectedAddress?->longitude
        );
        $deliveryFee = $pricing['delivery_fee'];
        $platformFee = $pricing['platform_fee'];
        $tax = $pricing['tax'];
        $total = $pricing['total'];
        
        $suggestedItems = $restaurant->menuItems()
            ->where('is_available', true)
            ->where(function ($query) use ($cartItems) {
                $query->where('is_bestseller', true)
                    ->orWhere('is_recommended', true)
                    ->orWhere('is_combo', true);
            })
            ->whereNotIn('id', collect($cartItems)->pluck('id')->filter()->all())
            ->limit(6)
            ->get();
        
        // Store in session
        session([
            'checkout_restaurant_id' => $restaurant->id,
            'checkout_cart' => $cart,
            'checkout_subtotal' => $subtotal
        ]);
        
        $appName = \App\Models\AppSetting::getValue('app_name', 'Food Delivery');
        $primaryColor = \App\Models\AppSetting::getValue('primary_color', '#EF4F5F');
        $primaryDark = '#E03546';
        $secondaryColor = \App\Models\AppSetting::getValue('secondary_color', '#FF8C42');
        $gatewayEnabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $gatewayProvider = AppSetting::getValue('payment_gateway_provider', 'razorpay');
        $walletBalance = optional(Wallet::where('user_id', Auth::id())->first())->balance ?? 0;
        $availableCoupons = PromoCode::query()
            ->where('is_active', true)
            ->where(function ($query) use ($restaurant) {
                $query->where('restaurant_id', $restaurant->id)
                    ->orWhereNull('restaurant_id');
            })
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->orderByRaw('COALESCE(min_order_amount, 0) asc')
            ->orderBy('code')
            ->get()
            ->filter(fn ($promo) => $promo->isEligibleForUser(Auth::id()))
            ->values();
        
        return view('checkout_fresh', compact(
            'restaurant', 'addresses', 'cartItems', 'subtotal', 
            'deliveryFee', 'platformFee', 'tax', 'total', 'appName',
            'primaryColor', 'primaryDark', 'secondaryColor', 'suggestedItems',
            'gatewayEnabled', 'gatewayProvider', 'walletBalance', 'availableCoupons'
        ));
    }
    
    public function process(Request $request)
    {
        Log::info('Checkout process started', [
            'restaurant_id' => $request->restaurant_id,
            'payment_method' => $request->payment_method,
        ]);
        $order = null;
        
        try {
            $request->validate([
                'restaurant_id' => 'required|exists:restaurants,id',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.selected_variant' => 'nullable|array',
                'items.*.selected_add_ons' => 'nullable|array',
                'order_type' => 'nullable|in:delivery,takeaway',
                'delivery_address_id' => 'nullable|exists:addresses,id',
                'payment_method' => 'required|in:cod,card,upi,wallet',
                'coupon_code' => 'nullable|string',
            ]);

            if ($request->payment_method === 'cod'
                && !filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cash on Delivery is currently unavailable. Please choose an online payment method or Wallet.',
                ], 422);
            }
            
            DB::beginTransaction();
            
            $gatewayEnabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
            if (in_array($request->payment_method, ['card', 'upi'], true) && !$gatewayEnabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Online payments are disabled by admin settings. Please choose Cash on Delivery or Wallet.',
                ], 422);
            }

            $restaurant = Restaurant::find($request->restaurant_id);
            $address = Address::find($request->delivery_address_id);
            
            if (!$restaurant) {
                throw new \Exception('Restaurant not found');
            }
            if (!$restaurant->is_open) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant is currently closed. Orders can only be placed when it reopens.'
                ], 422);
            }

            $orderType = strtolower($request->input('order_type', 'delivery'));
            if (!$restaurant->acceptsService($orderType)) {
                return response()->json([
                    'success' => false,
                    'message' => $orderType === 'takeaway'
                        ? 'This restaurant is not accepting takeaway orders.'
                        : 'This restaurant is not accepting delivery orders.',
                ], 422);
            }
            
            if ($orderType === 'delivery' && !$address) {
                throw new \Exception('Delivery address not found');
            }
            
            // Calculate subtotal
            $subtotal = 0;
            $orderItems = [];
            
            foreach ($request->items as $item) {
                $menuItem = MenuItem::find($item['id']);
                if (!$menuItem) {
                    throw new \Exception("Menu item not found: {$item['id']}");
                }
                
                $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
                $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
                $unitPrice = ($menuItem->discounted_price ?? $menuItem->price)
                    + ($variant['price'] ?? 0)
                    + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));
                $itemTotal = $unitPrice * $item['quantity'];
                $subtotal += $itemTotal;
                
                $orderItems[] = [
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                    'selected_variant' => $variant,
                    'selected_add_ons' => $addOns,
                ];
            }
            
            $deliveryFee = DeliveryChargeSetting::getDeliveryCharge();
            $platformFee = DeliveryChargeSetting::getPlatformFee();
            $tax = round((float) TaxSetting::calculateTax($subtotal, $deliveryFee), 2);
            $total = $subtotal + $deliveryFee + $platformFee + $tax;
            $discount = 0;
            $promo = null;

            if ($request->filled('coupon_code')) {
                $promo = PromoCode::where('code', $request->coupon_code)
                    ->where(function ($query) use ($restaurant) {
                        $query->where('restaurant_id', $restaurant->id)
                            ->orWhereNull('restaurant_id');
                    })
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $query->whereNull('start_date')->orWhereDate('start_date', '<=', now());
                    })
                    ->where(function ($query) {
                        $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
                    })
                    ->first();

                if (!$promo || !$promo->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or expired coupon code'
                    ], 400);
                }

                if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon usage limit exceeded'
                    ], 400);
                }

                if ($subtotal < $promo->min_order_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Minimum order amount of ' . AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required'
                    ], 400);
                }

                if (!$promo->isEligibleForUser(Auth::id())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This coupon is not eligible for your account'
                    ], 400);
                }

                $discount = round((float) $promo->calculateDiscount($subtotal), AppSetting::currencyDecimals());
                $total = max(0, $total - $discount);
            }
            
            $pricing = $this->buildPricingSummary(
                $restaurant,
                $subtotal,
                $address?->latitude,
                $address?->longitude,
                $request->coupon_code,
                $orderType
            );
            $deliveryFee = $pricing['delivery_fee'];
            $platformFee = $pricing['platform_fee'];
            $tax = $pricing['tax'];
            $discount = $pricing['discount'];
            $total = $pricing['total'];
            $promo = $pricing['promo'] ?? $promo;

            // Prepare customer address array
            $customerAddress = $orderType === 'takeaway'
                ? [
                    'name' => Auth::user()->name,
                    'address' => 'Takeaway from ' . $restaurant->name,
                    'city' => $restaurant->city,
                    'state' => $restaurant->state,
                    'pincode' => $restaurant->pincode,
                    'phone' => Auth::user()->phone,
                    'latitude' => $restaurant->latitude,
                    'longitude' => $restaurant->longitude,
                ]
                : [
                    'name' => $address->name,
                    'address' => $address->address,
                    'city' => $address->city,
                    'state' => $address->state,
                    'pincode' => $address->pincode,
                    'phone' => $address->phone,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                ];
            
            // Generate order number
            $orderNumber = $this->generateOrderNumber();
            
            // Create order with all required fields
            $order = Order::create([
                'order_number' => $orderNumber,
                'restaurant_id' => $restaurant->id,
                'customer_id' => Auth::id(),
                'order_type' => $orderType,
                'customer_name' => Auth::user()->name,
                'customer_phone' => $customerAddress['phone'],
                'customer_address' => json_encode($customerAddress), // This is the required field
                'delivery_address' => $customerAddress['address'] . ', ' . $customerAddress['city'] . ', ' . $customerAddress['state'] . ' - ' . $customerAddress['pincode'],
                'delivery_lat' => $customerAddress['latitude'] ?? null,
                'delivery_lng' => $customerAddress['longitude'] ?? null,
                'items' => json_encode($orderItems),
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
            ]);
            
            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'selected_variant' => $item['selected_variant'],
                    'selected_add_ons' => $item['selected_add_ons'],
                ]);
            }
            
            DB::commit();
            
            Log::info('Order created successfully', ['order_id' => $order->id]);

            if ($promo) {
                $promo->increment('used_count');
            }

            if ($request->payment_method === 'wallet') {
                $this->payWithWallet($order);
                $this->clearCheckoutState();
                $this->broadcastOrder($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Order placed successfully!',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'redirect_url' => route('customer.orders.track', $order->id),
                ]);
            }

            if ($request->payment_method === 'cod') {
                $this->clearCheckoutState();
                $this->broadcastOrder($order);

                return response()->json([
                    'success' => true,
                    'message' => 'Order placed successfully!',
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'redirect_url' => route('customer.orders.track', $order->id),
                ]);
            }

            $paymentPayload = $this->createGatewayPayment($order, $request->payment_method);
            
            return response()->json([
                'success' => true,
                'message' => 'Order created. Complete payment to confirm it.',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'requires_payment' => true,
                'payment' => $paymentPayload,
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            if ($order instanceof Order && $order->exists && $order->payment_status !== 'success') {
                $order->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Checkout setup failed: ' . $e->getMessage(),
                ]);
            }
            Log::error('Order creation failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:razorpay,stripe,cashfree',
            'payment_id' => 'required|string',
            'razorpay_order_id' => 'required_if:payment_method,razorpay|string',
            'razorpay_signature' => 'required_if:payment_method,razorpay|string',
            'stripe_payment_intent_id' => 'required_if:payment_method,stripe|string',
        ]);

        $order = Order::where('customer_id', Auth::id())->findOrFail($validated['order_id']);

        if ($order->payment_status === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Payment already verified.',
                'redirect_url' => route('customer.orders.track', $order->id),
            ]);
        }

        try {
            $paymentId = $this->verifyGatewayPayment($order, $validated);
        } catch (\RuntimeException $e) {
            Log::warning('Checkout payment verification pending or failed', [
                'order_id' => $order->id,
                'payment_method' => $validated['payment_method'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
        $this->markOrderAsPaid($order, $paymentId, $validated['payment_method']);
        $this->clearCheckoutState();
        $this->broadcastOrder($order->fresh(['restaurant', 'customer']));

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully.',
            'redirect_url' => route('customer.orders.track', $order->id),
        ]);
    }

    public function markPaymentFailed(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'message' => 'nullable|string|max:500',
        ]);

        $order = Order::where('customer_id', Auth::id())->findOrFail($validated['order_id']);

        if ($order->payment_status !== 'success') {
            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['message'] ?: 'Payment was cancelled before confirmation.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment failure recorded.',
        ]);
    }

    public function stripeSuccess(Request $request, Order $order, PaymentGatewayService $paymentGatewayService)
    {
        abort_unless($order->customer_id === Auth::id(), 403);

        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return redirect()->route('checkout.index')->with('error', 'Stripe session is missing.');
        }

        try {
            $session = $paymentGatewayService->retrieveStripeSession($sessionId);
            $paymentStatus = $session->payment_status ?? null;
            $paymentIntentId = $session->payment_intent ?? null;

            if ($paymentStatus !== 'paid' || !$paymentIntentId) {
                return redirect()->route('checkout.index')->with('error', 'Stripe payment is not completed yet.');
            }

            if ($order->payment_status !== 'success') {
                $this->markOrderAsPaid($order, (string) $paymentIntentId, 'stripe');
                $this->clearCheckoutState();
                $this->broadcastOrder($order->fresh(['restaurant', 'customer']));
            }

            return view('checkout.payment-redirect', [
                'redirectUrl' => route('customer.orders.track', $order->id),
                'message' => 'Payment completed successfully. Redirecting to your order...',
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe success verification failed', [
                'order_id' => $order->id,
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('checkout.index')->with('error', 'Unable to verify Stripe payment.');
        }
    }

    public function stripeCancel(Order $order)
    {
        if ($order->payment_status !== 'success') {
            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => 'Stripe checkout cancelled by customer.',
            ]);
        }

        return redirect()->route('checkout.index')->with('error', 'Stripe payment was cancelled. Please place the order again.');
    }

    public function webGatewayReturn(Request $request, string $provider, Order $order)
    {
        try {
            $paymentId = $this->verifyHostedGatewayPayment($provider, $order, $request);

            if ($order->payment_status !== 'success') {
                $this->markOrderAsPaid($order, $paymentId, $provider);
                $this->clearCheckoutState();
                $this->broadcastOrder($order->fresh(['restaurant', 'customer']));
            }

            return view('checkout.payment-redirect', [
                'redirectUrl' => Auth::check() && Auth::id() === $order->customer_id
                    ? route('customer.orders.track', $order->id)
                    : route('home'),
                'message' => ucfirst($provider) . ' payment completed successfully. Redirecting...',
            ]);
        } catch (\Throwable $e) {
            Log::error('Hosted gateway return verification failed', [
                'provider' => $provider,
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return redirect()->route('checkout.index')->with(
                'error',
                'Unable to verify ' . ucfirst($provider) . ' payment.'
            );
        }
    }

    public function webGatewayCancel(Request $request, string $provider, Order $order)
    {
        if ($order->payment_status !== 'success') {
            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => ucfirst($provider) . ' checkout cancelled by customer.',
            ]);
        }

        return redirect()->route('checkout.index')->with(
            'error',
            ucfirst($provider) . ' payment was cancelled. Please place the order again.'
        );
    }

    public function summary(Request $request)
    {
        $request->validate([
            'restaurant_id' => 'required|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_variant' => 'nullable|array',
            'items.*.selected_add_ons' => 'nullable|array',
            'order_type' => 'nullable|in:delivery,takeaway',
            'delivery_address_id' => 'nullable|exists:addresses,id',
            'coupon_code' => 'nullable|string|max:100',
        ]);

        $restaurant = Restaurant::findOrFail($request->restaurant_id);
        $address = $request->delivery_address_id ? Address::find($request->delivery_address_id) : null;
        $orderType = strtolower($request->input('order_type', 'delivery'));

        if (!$restaurant->acceptsService($orderType)) {
            return response()->json([
                'success' => false,
                'message' => $orderType === 'takeaway'
                    ? 'This restaurant is not accepting takeaway orders.'
                    : 'This restaurant is not accepting delivery orders.',
            ], 400);
        }

        $subtotal = 0;
        foreach ($request->items as $item) {
            $menuItem = MenuItem::find($item['id']);
            if (!$menuItem) {
                continue;
            }

            $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
            $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
            $unitPrice = ($menuItem->discounted_price ?? $menuItem->price)
                + ($variant['price'] ?? 0)
                + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));
            $subtotal += $unitPrice * $item['quantity'];
        }

        try {
            $pricing = $this->buildPricingSummary(
                $restaurant,
                $subtotal,
                $address?->latitude,
                $address?->longitude,
                $request->coupon_code,
                $orderType
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        unset($pricing['promo']);

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'subtotal' => round($subtotal, 2),
            ], $pricing),
        ]);
    }
    
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100',
            'subtotal' => 'required|numeric|min:0',
            'restaurant_id' => 'nullable|exists:restaurants,id',
        ]);

        $restaurantId = $request->restaurant_id ?: session('checkout_restaurant_id');
        if (!$restaurantId) {
            $cart = session('checkout_cart', []);
            $restaurantId = $cart[0]['restaurant_id'] ?? null;
        }

        if (!$restaurantId) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to validate coupon without restaurant context.'
            ], 400);
        }

        $promo = PromoCode::where('code', $request->code)
            ->where(function ($query) use ($restaurantId) {
                $query->where('restaurant_id', $restaurantId)
                    ->orWhereNull('restaurant_id');
            })
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            })
            ->first();

        if (!$promo || !$promo->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired coupon code'
            ], 400);
        }

        if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon usage limit exceeded'
            ], 400);
        }

        if ($request->subtotal < $promo->min_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount of ' . AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required'
            ], 400);
        }

        if (!$promo->isEligibleForUser(Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is not eligible for your account'
            ], 400);
        }

        $discount = round((float) $promo->calculateDiscount($request->subtotal), AppSetting::currencyDecimals());
        if ($discount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon does not apply to the current order total'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'discount' => $discount,
            'coupon_code' => $promo->code,
        ]);
    }
    
    public function saveCart(Request $request)
    {
        session(['checkout_cart' => $request->cart]);
        session(['checkout_restaurant_id' => $request->restaurant_id]);
        return response()->json(['success' => true]);
    }

    private function buildPricingSummary(
        Restaurant $restaurant,
        float $subtotal,
        $deliveryLat = null,
        $deliveryLng = null,
        ?string $couponCode = null,
        string $orderType = 'delivery'
    ): array {
        $deliveryFee = $this->calculateDeliveryFee($restaurant, $deliveryLat, $deliveryLng, $subtotal, $orderType);
        $platformFee = DeliveryChargeSetting::getPlatformFee();

        $activeTaxes = TaxSetting::getActiveTaxes();
        if ($activeTaxes->isEmpty()) {
            $tax = 0.0;
            $taxRate = 0.0;
            $taxLabel = 'Taxes & charges';
            $taxBreakdown = [];
        } else {
            $taxRate = (float) $activeTaxes->sum('rate');
            $taxBreakdown = TaxSetting::calculateBreakdown($subtotal, $deliveryFee);
            $tax = round((float) collect($taxBreakdown)->sum('amount'), 2);
            $taxLabel = 'Taxes & charges';
        }

        $discount = 0.0;
        $promo = null;

        if ($couponCode) {
            $promo = PromoCode::where('code', $couponCode)
                ->where(function ($query) use ($restaurant) {
                    $query->where('restaurant_id', $restaurant->id)
                        ->orWhereNull('restaurant_id');
                })
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('start_date')->orWhereDate('start_date', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('end_date')->orWhereDate('end_date', '>=', now());
                })
                ->first();

            if (!$promo || !$promo->isValid()) {
                throw new \InvalidArgumentException('Invalid or expired coupon code');
            }

            if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
                throw new \InvalidArgumentException('Coupon usage limit exceeded');
            }

            if ($subtotal < $promo->min_order_amount) {
                throw new \InvalidArgumentException(
                    'Minimum order amount of ' . \App\Models\AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required'
                );
            }

            if (!$promo->isEligibleForUser(Auth::id())) {
                throw new \InvalidArgumentException('This coupon is not eligible for your account');
            }

            $discount = round((float) $promo->calculateDiscount($subtotal), AppSetting::currencyDecimals());
        }

        return [
            'delivery_fee' => round($deliveryFee, 2),
            'order_type' => $orderType,
            'platform_fee' => $platformFee,
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'tax_label' => $taxLabel,
            'tax_breakdown' => $taxBreakdown,
            'discount' => $discount,
            'total' => max(0, round($subtotal + $deliveryFee + $platformFee + $tax - $discount, 2)),
            'promo' => $promo,
        ];
    }

    private function calculateDeliveryFee(Restaurant $restaurant, $deliveryLat = null, $deliveryLng = null, float $subtotal = 0, string $orderType = 'delivery'): float
    {
        if ($orderType === 'takeaway') {
            return 0.0;
        }

        $distance = null;

        if ($deliveryLat !== null && $deliveryLng !== null && $restaurant->latitude && $restaurant->longitude) {
            $distance = $this->calculateDistance($restaurant->latitude, $restaurant->longitude, $deliveryLat, $deliveryLng);
        }

        $freeDeliveryThreshold = DeliveryChargeSetting::getFreeDeliveryThreshold($restaurant->id, $deliveryLat, $deliveryLng);
        if ($freeDeliveryThreshold !== null && $subtotal >= (float) $freeDeliveryThreshold) {
            return 0.0;
        }

        return round((float) DeliveryChargeSetting::getDeliveryCharge($distance), 2);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $kilometers = $miles * 1.609344;

        return round($kilometers, 2);
    }
    
    private function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = mt_rand(1000, 9999);
        $orderNumber = $prefix . $date . $random;
        
        while (Order::where('order_number', $orderNumber)->exists()) {
            $random = mt_rand(1000, 9999);
            $orderNumber = $prefix . $date . $random;
        }
        
        return $orderNumber;
    }

    private function resolveSelectedOption(array $options, ?array $selected): ?array
    {
        if (!$selected || empty($selected['name'])) {
            return null;
        }

        return collect($options)
            ->filter(fn ($option) => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN))
            ->firstWhere('name', $selected['name']);
    }

    private function resolveSelectedAddOns(array $options, array $selected): array
    {
        $names = collect($selected)->pluck('name')->filter()->all();

        return collect($options)
            ->filter(fn ($option) => filter_var($option['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN))
            ->filter(fn ($option) => in_array($option['name'] ?? null, $names, true))
            ->values()
            ->all();
    }

    private function createGatewayPayment(Order $order, string $requestedMethod): array
    {
        $gatewayEnabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        if (!$gatewayEnabled) {
            throw new \RuntimeException('Online payments are currently disabled in admin settings.');
        }

        $provider = strtolower(AppSetting::getValue('payment_gateway_provider', 'razorpay'));

        return match ($provider) {
            'stripe' => $this->createStripePayment($order),
            'razorpay' => $this->createRazorpayPayment($order),
            'cashfree' => $this->createCashfreePayment($order),
            'paystack' => $this->createPaystackPayment($order),
            'mollie' => $this->createMolliePayment($order),
            'mercadopago' => $this->createMercadoPagoPayment($order),
            default => throw new \RuntimeException('Unsupported payment gateway provider: ' . $provider),
        };
    }

    private function createStripePayment(Order $order): array
    {
        $paymentGatewayService = app(PaymentGatewayService::class);
        $session = $paymentGatewayService->createStripeCheckoutSession(
            $order,
            route('payment.stripe.success', $order) . '?session_id={CHECKOUT_SESSION_ID}',
            route('payment.stripe.cancel', $order),
        );

        return [
            'provider' => 'stripe',
            'redirect_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    private function createRazorpayPayment(Order $order): array
    {
        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        if (!$key || !$secret) {
            throw new \RuntimeException('Razorpay is not configured in admin settings.');
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->asJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'receipt' => 'order_' . $order->order_number,
                'amount' => (int) round($order->total * 100),
                'currency' => $currency,
                'payment_capture' => 1,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to create Razorpay order.');
        }

        $payload = $response->json();

        return [
            'provider' => 'razorpay',
            'key' => $key,
            'order_id' => $payload['id'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'] ?? 'INR',
            'name' => AppSetting::getValue('app_name', config('app.name', 'FoodFlow')),
            'description' => 'Food order payment',
            'prefill' => [
                'name' => $order->customer_name,
                'email' => optional($order->customer)->email,
                'contact' => $order->customer_phone,
            ],
            'theme' => [
                'color' => AppSetting::getValue('primary_color', '#EF4F5F'),
            ],
        ];
    }

    private function createCashfreePayment(Order $order): array
    {
        $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
        $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
        $apiVersion = config('services.cashfree.api_version', '2022-09-01');
        $mode = AppSetting::getValue('cashfree_mode', 'test');

        if (!$clientId || !$clientSecret) {
            throw new \RuntimeException('Cashfree is not configured in admin settings.');
        }

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
        $cashfreeOrderId = 'ORDER_' . $order->id . '_' . time();
        $response = Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ])->post($this->cashfreeBaseUrl() . '/pg/orders', [
            'order_id' => $cashfreeOrderId,
            'order_amount' => round((float) $order->total, 2),
            'order_currency' => $currency,
            'customer_details' => [
                'customer_id' => 'CUST_' . $order->customer_id,
                'customer_email' => optional($order->customer)->email ?? '',
                'customer_phone' => $order->customer_phone,
                'customer_name' => $order->customer_name,
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to create Cashfree order.');
        }

        $payload = $response->json();

        return [
            'provider' => 'cashfree',
            'order_id' => $payload['order_id'] ?? $cashfreeOrderId,
            'payment_session_id' => $payload['payment_session_id'] ?? null,
            'environment' => $mode === 'test' ? 'sandbox' : 'production',
        ];
    }

    private function createPaystackPayment(Order $order): array
    {
        $secretKey = AppSetting::getValue('paystack_secret_key');
        $currency = strtoupper(AppSetting::getValue('currency_code', 'NGN') ?: 'NGN');

        if (!$secretKey) {
            throw new \RuntimeException('Paystack is not configured in admin settings.');
        }

        $reference = 'PAYSTACK_' . $order->order_number . '_' . time();
        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => optional($order->customer)->email ?: 'customer' . $order->customer_id . '@example.com',
                'amount' => (int) round($order->total * 100),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => route('payment.web.return', ['provider' => 'paystack', 'order' => $order]),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to create Paystack payment.');
        }

        $payload = $response->json('data') ?? [];
        $order->update(['payment_id' => $reference]);

        return [
            'provider' => 'paystack',
            'redirect_url' => $payload['authorization_url'] ?? null,
            'reference' => $reference,
        ];
    }

    private function createMolliePayment(Order $order): array
    {
        $apiKey = AppSetting::getValue('mollie_key');
        $currency = strtoupper(AppSetting::getValue('currency_code', 'EUR') ?: 'EUR');

        if (!$apiKey) {
            throw new \RuntimeException('Mollie is not configured in admin settings.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post('https://api.mollie.com/v2/payments', [
                'amount' => [
                    'currency' => $currency,
                    'value' => number_format((float) $order->total, AppSetting::currencyDecimals(), '.', ''),
                ],
                'description' => 'Order #' . $order->order_number,
                'redirectUrl' => route('payment.web.return', ['provider' => 'mollie', 'order' => $order]),
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => (string) $order->order_number,
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to create Mollie payment.');
        }

        $payload = $response->json();
        $order->update(['payment_id' => $payload['id'] ?? null]);

        return [
            'provider' => 'mollie',
            'redirect_url' => data_get($payload, '_links.checkout.href'),
            'payment_id' => $payload['id'] ?? null,
        ];
    }

    private function createMercadoPagoPayment(Order $order): array
    {
        $accessToken = AppSetting::getValue('mercadopago_access_token');
        $currency = strtoupper(AppSetting::getValue('currency_code', 'BRL') ?: 'BRL');
        $mode = AppSetting::getValue('mercadopago_mode', 'test');

        if (!$accessToken) {
            throw new \RuntimeException('Mercado Pago is not configured in admin settings.');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => [[
                    'title' => 'Order #' . $order->order_number,
                    'quantity' => 1,
                    'currency_id' => $currency,
                    'unit_price' => round((float) $order->total, AppSetting::currencyDecimals()),
                ]],
                'external_reference' => (string) $order->id,
                'back_urls' => [
                    'success' => route('payment.web.return', ['provider' => 'mercadopago', 'order' => $order]),
                    'failure' => route('payment.web.cancel', ['provider' => 'mercadopago', 'order' => $order]),
                    'pending' => route('payment.web.return', ['provider' => 'mercadopago', 'order' => $order]),
                ],
                'auto_return' => 'approved',
                'notification_url' => route('payment.web.return', ['provider' => 'mercadopago', 'order' => $order]),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Unable to create Mercado Pago payment.');
        }

        $payload = $response->json();

        return [
            'provider' => 'mercadopago',
            'redirect_url' => $mode === 'live'
                ? ($payload['init_point'] ?? null)
                : ($payload['sandbox_init_point'] ?? $payload['init_point'] ?? null),
            'preference_id' => $payload['id'] ?? null,
        ];
    }

    private function verifyGatewayPayment(Order $order, array $validated): string
    {
        $paymentMethod = strtolower($validated['payment_method']);

        if ($paymentMethod === 'razorpay') {
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (!$secret) {
                throw new \RuntimeException('Razorpay is not configured.');
            }

            $payload = $validated['razorpay_order_id'] . '|' . $validated['payment_id'];
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expectedSignature, $validated['razorpay_signature'])) {
                throw new \RuntimeException('Payment signature verification failed.');
            }

            return $validated['payment_id'];
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (!$stripeSecret) {
                throw new \RuntimeException('Stripe is not configured.');
            }

            Stripe::setApiKey($stripeSecret);
            $paymentIntent = PaymentIntent::retrieve($validated['stripe_payment_intent_id']);

            if (!in_array($paymentIntent->status, ['succeeded', 'processing'], true)) {
                throw new \RuntimeException('Stripe payment was not successful. Status: ' . $paymentIntent->status);
            }

            if ((int) $paymentIntent->amount !== (int) round($order->total * 100)) {
                throw new \RuntimeException('Payment amount does not match order total.');
            }

            return $validated['stripe_payment_intent_id'];
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (!$clientId || !$clientSecret) {
                throw new \RuntimeException('Cashfree is not configured.');
            }

            $expectedPrefix = 'ORDER_' . $order->id . '_';
            if (!str_starts_with($validated['payment_id'], $expectedPrefix)) {
                throw new \RuntimeException('Cashfree order does not match this checkout.');
            }

            $response = null;
            $cashfreeOrder = [];
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $response = Http::withHeaders([
                    'x-api-version' => $apiVersion,
                    'x-client-id' => $clientId,
                    'x-client-secret' => $clientSecret,
                ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $validated['payment_id']);

                if ($response->successful()) {
                    $cashfreeOrder = $response->json();
                    if (strtoupper((string) ($cashfreeOrder['order_status'] ?? '')) === 'PAID') {
                        break;
                    }
                }

                if ($attempt < 3) {
                    usleep(750000);
                }
            }

            if (!$response || $response->failed()) {
                throw new \RuntimeException('Unable to verify Cashfree payment.');
            }

            $cashfreeStatus = strtoupper((string) ($cashfreeOrder['order_status'] ?? 'UNKNOWN'));
            if ($cashfreeStatus !== 'PAID') {
                throw new \RuntimeException('Cashfree payment is not confirmed yet. Status: ' . $cashfreeStatus . '.');
            }

            $paymentsResponse = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $validated['payment_id'] . '/payments');
            $payments = $paymentsResponse->successful() ? $paymentsResponse->json() : [];
            if (isset($payments['payments'])) {
                $payments = $payments['payments'];
            }
            $successfulPayment = collect($payments)->first(
                fn ($payment) => strtoupper((string) ($payment['payment_status'] ?? '')) === 'SUCCESS'
            );

            $reportedAmount = $successfulPayment['payment_amount']
                ?? $cashfreeOrder['order_amount']
                ?? null;
            if ($reportedAmount === null) {
                throw new \RuntimeException('Cashfree did not return the paid amount for verification.');
            }

            $paidMinor = (int) round(((float) $reportedAmount) * 100);
            $expectedMinor = (int) round(((float) $order->total) * 100);
            if ($paidMinor !== $expectedMinor) {
                Log::warning('Cashfree paid amount mismatch', [
                    'order_id' => $order->id,
                    'cashfree_order_id' => $validated['payment_id'],
                    'paid_amount' => $reportedAmount,
                    'expected_amount' => $order->total,
                ]);
            }

            return $successfulPayment['cf_payment_id'] ?? $validated['payment_id'];
        }

        if ($paymentMethod === 'paystack') {
            $secretKey = AppSetting::getValue('paystack_secret_key');
            if (!$secretKey) {
                throw new \RuntimeException('Paystack is not configured.');
            }

            $reference = $validated['payment_id'];
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->get('https://api.paystack.co/transaction/verify/' . $reference);

            if ($response->failed()) {
                throw new \RuntimeException('Unable to verify Paystack payment.');
            }

            $payment = $response->json('data') ?? [];
            if (($payment['status'] ?? null) !== 'success') {
                throw new \RuntimeException('Paystack payment was not successful.');
            }

            return $payment['reference'] ?? $reference;
        }

        if ($paymentMethod === 'mollie') {
            $apiKey = AppSetting::getValue('mollie_key');
            $paymentId = $order->payment_id ?: $validated['payment_id'];

            if (!$apiKey || !$paymentId) {
                throw new \RuntimeException('Mollie is not configured.');
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->get('https://api.mollie.com/v2/payments/' . $paymentId);

            if ($response->failed()) {
                throw new \RuntimeException('Unable to verify Mollie payment.');
            }

            $payment = $response->json();
            if (($payment['status'] ?? null) !== 'paid') {
                throw new \RuntimeException('Mollie payment is not completed yet.');
            }

            return $payment['id'] ?? $paymentId;
        }

        if ($paymentMethod === 'mercadopago') {
            $accessToken = AppSetting::getValue('mercadopago_access_token');
            $paymentId = $validated['payment_id'];

            if (!$accessToken || !$paymentId) {
                throw new \RuntimeException('Mercado Pago is not configured.');
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get('https://api.mercadopago.com/v1/payments/' . $paymentId);

            if ($response->failed()) {
                throw new \RuntimeException('Unable to verify Mercado Pago payment.');
            }

            $payment = $response->json();
            if (!in_array($payment['status'] ?? null, ['approved', 'authorized'], true)) {
                throw new \RuntimeException('Mercado Pago payment is not approved.');
            }

            return (string) ($payment['id'] ?? $paymentId);
        }

        throw new \RuntimeException('Unsupported payment verification method.');
    }

    private function markOrderAsPaid(Order $order, string $paymentId, string $paymentMethod): void
    {
        DB::transaction(function () use ($order, $paymentId, $paymentMethod) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->payment_status === 'success') {
                return;
            }

            $lockedOrder->update([
                'payment_status' => 'success',
                'payment_id' => $paymentId,
                'payment_method' => $paymentMethod,
                'online_payment_verified_at' => now(),
            ]);

            Transaction::firstOrCreate(
                [
                    'order_id' => $lockedOrder->id,
                    'transaction_id' => $paymentId,
                ],
                [
                    'user_id' => Auth::id(),
                    'amount' => (float) $lockedOrder->total,
                    'type' => 'payment',
                    'status' => 'success',
                    'payment_method' => $paymentMethod,
                ]
            );
        });
    }

    private function payWithWallet(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->payment_status === 'success') {
                return;
            }

            $wallet = Wallet::where('user_id', Auth::id())->lockForUpdate()->first();
            if (!$wallet || $wallet->balance < (float) $lockedOrder->total) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance', (float) $lockedOrder->total);
            $wallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => Auth::id(),
                'type' => 'debit',
                'amount' => (float) $lockedOrder->total,
                'balance_after' => (float) $wallet->balance,
                'reference_type' => 'order',
                'reference_id' => $lockedOrder->id,
                'description' => "Order #{$lockedOrder->order_number}",
            ]);

            $lockedOrder->update([
                'payment_status' => 'success',
                'payment_id' => 'wallet_' . $lockedOrder->id,
                'online_payment_verified_at' => now(),
            ]);
        });
    }

    private function clearCheckoutState(): void
    {
        session()->forget(['checkout_cart', 'checkout_restaurant_id', 'checkout_subtotal']);
    }

    private function broadcastOrder(Order $order): void
    {
        app(OrderReleaseService::class)->releaseToRestaurant($order);
    }

    private function notifyRestaurantAboutNewOrder(Order $order): void
    {
        $order->loadMissing(['restaurant.owner', 'customer']);
        $restaurant = $order->restaurant;
        if (! $restaurant) {
            return;
        }

        $title = 'New order received';
        $body = "Order #{$order->order_number} has been placed for your restaurant.";
        $items = is_array($order->items) ? $order->items : json_decode((string) $order->items, true);
        $payload = [
            'type' => 'NEW_ORDER',
            'role' => 'restaurant',
            'timer_duration' => '30',
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'restaurant_id' => (string) $order->restaurant_id,
            'restaurant_name' => (string) $restaurant->name,
            'pickup' => (string) $restaurant->address,
            'customer_name' => (string) ($order->customer_name ?? $order->customer?->name ?? 'Guest'),
            'customer_phone' => (string) ($order->customer_phone ?? ''),
            'delivery_address' => (string) ($order->delivery_address ?? ''),
            'amount' => (string) $order->total,
            'total' => (string) $order->total,
            'items' => json_encode($items ?? []),
            'metadata' => json_encode([
                'pickup' => $restaurant->address,
                'items' => $items ?? [],
                'amount' => (float) $order->total,
            ]),
        ];

        $recipients = collect([$restaurant->owner])
            ->filter()
            ->merge(
                    RestaurantStaff::query()
                        ->where('restaurant_id', $restaurant->id)
                        ->where('is_active', true)
                        ->with('user:id,fcm_token,restaurant_fcm_token')
                        ->get()
                    ->pluck('user')
                    ->filter()
            )
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            $recipient->notify(new AppDatabaseNotification($title, $body, $payload));
        }

        (new FirebaseHelper())->sendToDevices(
            $recipients
                ->map(fn ($user) => $user->fcmTokenForApp('restaurant'))
                ->filter(fn ($token) => filled($token))
                ->unique()
                ->values()
                ->all(),
            $title,
            $body,
            $payload
        );
    }

    private function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'test') === 'live'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com';
    }

    private function verifyHostedGatewayPayment(string $provider, Order $order, Request $request): string
    {
        return match (strtolower($provider)) {
            'paystack' => $this->verifyGatewayPayment($order, [
                'payment_method' => 'paystack',
                'payment_id' => $request->query('reference', $order->payment_id),
            ]),
            'mollie' => $this->verifyGatewayPayment($order, [
                'payment_method' => 'mollie',
                'payment_id' => $order->payment_id,
            ]),
            'mercadopago' => $this->verifyGatewayPayment($order, [
                'payment_method' => 'mercadopago',
                'payment_id' => (string) ($request->query('payment_id')
                    ?? $request->query('collection_id')
                    ?? $request->input('data.id')
                    ?? ''),
            ]),
            default => throw new \RuntimeException('Unsupported hosted gateway callback: ' . $provider),
        };
    }
}
