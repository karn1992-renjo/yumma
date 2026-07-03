<?php

// app/Http/Controllers/Api/OrderController.php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderEvent;
use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppSetting;
use App\Models\DeliveryChargeSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCancellationLimit;
use App\Models\OrderItem;
use App\Models\PromoCode;
use App\Models\RefundPolicy;
use App\Models\Restaurant;
use App\Models\RestaurantStaff;
use App\Models\Review;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\AppDatabaseNotification;
use App\Services\GoogleMapsEtaService;
use App\Services\MediaStorage;
use App\Services\OrderReleaseService;
use App\Services\OrderStatusPushService;
use App\Services\PrinterService;
use App\Services\RefundService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_variant' => 'nullable|array',
            'items.*.selected_add_ons' => 'nullable|array',
            'order_type' => 'nullable|in:delivery,takeaway',
            'delivery_address_id' => 'nullable|exists:addresses,id',
            'delivery_address' => 'nullable|string',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'payment_method' => 'required|in:cod,card,upi,wallet,razorpay,stripe,cashfree',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'special_instructions' => 'nullable|string|max:1000',
            'scheduled_time' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->payment_method === 'cod'
            && ! filter_var(AppSetting::getValue('cod_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'message' => 'Cash on Delivery is currently unavailable. Please choose an online payment method or Wallet.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $restaurant = Restaurant::find($request->restaurant_id);
            if (! $restaurant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.',
                ], 404);
            }
            if (! $restaurant->isOpenNow()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant is currently closed. Orders cannot be placed until it reopens.',
                ], 400);
            }

            $orderType = strtolower($request->input('order_type', 'delivery'));
            if (! $restaurant->acceptsService($orderType)) {
                return response()->json([
                    'success' => false,
                    'message' => $orderType === 'takeaway'
                        ? 'This restaurant is not accepting takeaway orders.'
                        : 'This restaurant is not accepting delivery orders.',
                ], 400);
            }

            if ($orderType === 'delivery' && ! $request->delivery_address_id && ! $request->filled('delivery_address')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select or enter a delivery address.',
                ], 422);
            }

            $subtotal = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $menuItem = MenuItem::find($item['id']);
                $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
                $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
                $unitPrice = $menuItem->getFinalPriceAttribute()
                    + ($variant['price'] ?? 0)
                    + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));
                $itemTotal = $unitPrice * $item['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'selected_variant' => $variant,
                    'selected_add_ons' => $addOns,
                    'total' => $itemTotal,
                ];
            }

            $deliveryFee = $this->calculateDeliveryFee(
                $request->restaurant_id,
                $request->delivery_lat,
                $request->delivery_lng,
                $subtotal,
                $orderType
            );
            $platformFee = DeliveryChargeSetting::getPlatformFee();
            $tax = round((float) TaxSetting::calculateTax($subtotal, $deliveryFee), 2);
            $total = $subtotal + $deliveryFee + $platformFee + $tax;
            $discount = 0;
            $promo = null;

            if ($request->filled('coupon_code')) {
                $promo = PromoCode::where('code', $request->coupon_code)
                    ->where(function ($query) use ($request) {
                        $query->where('restaurant_id', $request->restaurant_id)
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

                if (! $promo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or expired coupon code',
                    ], 400);
                }

                if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Coupon usage limit exceeded',
                    ], 400);
                }

                if ($subtotal < $promo->min_order_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Minimum order amount of ' . AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required',
                    ], 400);
                }

                if (method_exists($promo, 'isEligibleForUser') && ! $promo->isEligibleForUser(auth()->id())) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This coupon is not eligible for your account',
                    ], 400);
                }

                $discount = round((float) $promo->calculateDiscount($subtotal), AppSetting::currencyDecimals());
                $total = max(0, $total - $discount);
            }

            $deliveryAddress = $request->delivery_address;
            $deliveryLat = $request->delivery_lat;
            $deliveryLng = $request->delivery_lng;
            $address = null;

            if ($request->delivery_address_id) {
                $address = Address::find($request->delivery_address_id);
                if ($address) {
                    $deliveryAddress = trim("{$address->address}, {$address->city}, {$address->pincode}");
                    $deliveryLat = $address->latitude ?? $deliveryLat;
                    $deliveryLng = $address->longitude ?? $deliveryLng;
                }
            }

            try {
                $pricing = $this->buildPricingSummary(
                    $restaurant,
                    $subtotal,
                    $deliveryLat,
                    $deliveryLng,
                    $request->coupon_code,
                    $orderType
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            $deliveryFee = $pricing['delivery_fee'];
            $tax = $pricing['tax'];
            $platformFee = $pricing['platform_fee'];
            $discount = $pricing['discount'];
            $total = $pricing['total'];
            $promo = $pricing['promo'];

            $customerName = $request->customer_name ?: auth()->user()->name;
            $customerPhone = $request->customer_phone ?: auth()->user()->phone;
            if (preg_match('/[A-Za-z]/', (string) $customerPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid mobile number for the selected country code.',
                ], 422);
            }
            $customerPhone = PhoneNumber::normalize(
                $customerPhone,
                AppSetting::getValue('default_mobile_country_code', '+91')
            );
            if ($customerPhone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Enter a valid mobile number for the selected country code.',
                ], 422);
            }

            if ($orderType === 'takeaway') {
                $deliveryAddress = 'Takeaway from ' . $restaurant->name;
                $deliveryLat = $restaurant->latitude;
                $deliveryLng = $restaurant->longitude;
            }

            $customerAddress = [
                'name' => $customerName,
                'address' => $deliveryAddress,
                'city' => $address ? $address->city : null,
                'state' => $address ? $address->state : null,
                'pincode' => $address ? $address->pincode : null,
                'phone' => $customerPhone,
                'latitude' => $deliveryLat,
                'longitude' => $deliveryLng,
            ];

            $order = Order::create([
                'customer_id' => auth()->id(),
                'restaurant_id' => $request->restaurant_id,
                'order_type' => $orderType,
                'items' => $orderItems,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'status' => 'pending',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'delivery_address' => $deliveryAddress,
                'delivery_lat' => $deliveryLat,
                'delivery_lng' => $deliveryLng,
                'scheduled_time' => $request->filled('scheduled_time')
                    ? $request->date('scheduled_time')
                    : null,
                'order_number' => $this->generateOrderNumber(),
                'delivery_otp' => random_int(1000, 9999),
                'special_instructions' => $request->input('special_instructions'),
            ]);

            if ($request->payment_method === 'wallet') {
                $wallet = Wallet::where('user_id', auth()->id())->lockForUpdate()->first();
                if (! $wallet || $wallet->balance < $total) {
                    throw new \Exception('Insufficient wallet balance.');
                }

                $wallet->decrement('balance', $total);
                $wallet->refresh();

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => auth()->id(),
                    'type' => 'debit',
                    'amount' => $total,
                    'balance_after' => $wallet->balance,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'description' => "Order #{$order->order_number}",
                ]);

                $order->update(['payment_status' => 'success']);
            }

            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['total'],
                    'selected_variant' => $item['selected_variant'],
                    'selected_add_ons' => $item['selected_add_ons'],
                ]);
            }

            if ($promo) {
                $promo->increment('used_count');
            }

            DB::commit();

            if ($order->payment_status === 'success') {
                app(OrderStatusPushService::class)->notifyCustomer(
                    $order->fresh(['customer', 'restaurant']),
                    "Payment confirmed. Your order #{$order->order_number} has been placed successfully."
                );
            }

            app(OrderReleaseService::class)->releaseToRestaurant($order);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data' => [
                    'order' => $order,
                    'order_number' => $order->order_number,
                    'total' => $total,
                    'platform_fee' => $platformFee,
                    'refund_policy' => $this->getRefundPolicySummary(),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to place order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $order = Order::with(['restaurant', 'driver'])
            ->where('customer_id', auth()->id())
            ->findOrFail($id);

        if (($order->order_type ?? 'delivery') !== 'takeaway' &&
            ! $order->delivery_otp &&
            ! in_array($order->status, ['delivered', 'cancelled'])) {
            $order->generateDeliveryOtp();
        }

        $this->attachRefundPresentation($order);

        return response()->json([
            'success' => true,
            'data' => $order
                ? $this->appendEtaToOrderPayload($order)
                : null,
        ]);
    }

    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_variant' => 'nullable|array',
            'items.*.selected_add_ons' => 'nullable|array',
            'order_type' => 'nullable|in:delivery,takeaway',
            'delivery_address_id' => 'nullable|exists:addresses,id',
            'delivery_lat' => 'nullable|numeric',
            'delivery_lng' => 'nullable|numeric',
            'coupon_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $restaurant = Restaurant::find($request->restaurant_id);
        if (! $restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant not found.',
            ], 404);
        }

        $orderType = strtolower($request->input('order_type', 'delivery'));
        if (! $restaurant->acceptsService($orderType)) {
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
            $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
            $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
            $unitPrice = $menuItem->getFinalPriceAttribute()
                + ($variant['price'] ?? 0)
                + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));

            $subtotal += $unitPrice * $item['quantity'];
        }

        $deliveryLat = $request->delivery_lat;
        $deliveryLng = $request->delivery_lng;

        if ($request->delivery_address_id) {
            $address = Address::find($request->delivery_address_id);
            if ($address) {
                $deliveryLat = $address->latitude ?? $deliveryLat;
                $deliveryLng = $address->longitude ?? $deliveryLng;
            }
        }

        try {
            $pricing = $this->buildPricingSummary(
                $restaurant,
                $subtotal,
                $deliveryLat,
                $deliveryLng,
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
        $freeDeliveryThreshold = $orderType === 'delivery'
            ? DeliveryChargeSetting::getFreeDeliveryThreshold(
                $restaurant->id,
                $deliveryLat,
                $deliveryLng
            )
            : null;
        $freeDeliveryRemaining = $freeDeliveryThreshold !== null
            ? max(0, (float) $freeDeliveryThreshold - (float) $subtotal)
            : null;
        $deliveryDistanceKm = null;
        if ($orderType !== 'takeaway' &&
            $deliveryLat !== null &&
            $deliveryLng !== null &&
            $restaurant->latitude !== null &&
            $restaurant->longitude !== null) {
            $deliveryDistanceKm = $this->calculateDistance(
                (float) $restaurant->latitude,
                (float) $restaurant->longitude,
                (float) $deliveryLat,
                (float) $deliveryLng
            );
        }

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'subtotal' => round($subtotal, 2),
                'delivery_distance_km' => $deliveryDistanceKm,
                'free_delivery_threshold' => $freeDeliveryThreshold !== null
                    ? round((float) $freeDeliveryThreshold, 2)
                    : null,
                'free_delivery_remaining' => $freeDeliveryRemaining !== null
                    ? round($freeDeliveryRemaining, 2)
                    : null,
                'free_delivery_eligible' => $freeDeliveryThreshold !== null,
                'free_delivery_achieved' => $freeDeliveryThreshold !== null
                    && $freeDeliveryRemaining <= 0,
            ], $pricing, [
                'eta' => app(GoogleMapsEtaService::class)->estimateDelivery(
                    $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
                    $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
                    $deliveryLat !== null ? (float) $deliveryLat : null,
                    $deliveryLng !== null ? (float) $deliveryLng : null,
                    (int) ($restaurant->order_lead_time ?? 20)
                ),
            ]),
        ]);
    }

    public function submitFeedback(Request $request, $id)
    {
        $order = Order::with('restaurant')
            ->where('customer_id', auth()->id())
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Feedback can be submitted only after delivery.',
            ], 422);
        }

        $validated = $request->validate([
            'restaurant_rating' => 'required|integer|min:1|max:5',
            'driver_rating' => 'nullable|integer|min:1|max:5',
            'item_rating' => 'nullable|integer|min:1|max:5',
            'service_rating' => 'nullable|integer|min:1|max:5',
            'restaurant_feedback' => 'nullable|string|max:1000',
            'driver_feedback' => 'nullable|string|max:1000',
            'item_feedback' => 'nullable|string|max:1000',
            'service_feedback' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $order->update([
                'restaurant_rating' => $validated['restaurant_rating'],
                'driver_rating' => $validated['driver_rating'] ?? null,
                'item_rating' => $validated['item_rating'] ?? null,
                'service_rating' => $validated['service_rating'] ?? null,
                'restaurant_feedback' => $validated['restaurant_feedback'] ?? null,
                'driver_feedback' => $validated['driver_feedback'] ?? null,
                'item_feedback' => $validated['item_feedback'] ?? null,
                'service_feedback' => $validated['service_feedback'] ?? null,
                'feedback_submitted_at' => now(),
            ]);

            Review::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'order_id' => $order->id,
                    'restaurant_id' => $order->restaurant_id,
                ],
                [
                    'rating' => $validated['restaurant_rating'],
                    'comment' => $validated['restaurant_feedback'] ?? null,
                    'is_verified' => true,
                    'status' => 'approved',
                ]
            );

            $ratingSummary = Review::where('restaurant_id', $order->restaurant_id)
                ->where('status', 'approved')
                ->where('is_verified', true)
                ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_reviews')
                ->first();

            if ($order->restaurant && $ratingSummary) {
                $totalReviews = (int) $ratingSummary->total_reviews;
                $order->restaurant->update([
                    'rating' => $totalReviews >= 3
                        ? round((float) $ratingSummary->average_rating, 1)
                        : 0,
                    'total_ratings' => $totalReviews,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thanks for your feedback!',
                'data' => $order->fresh(['restaurant', 'driver']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order feedback error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, $id)
    {
        $order = Order::where('customer_id', auth()->id())
            ->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $canInstantCancel = $order->isCancellable();
        $canForceCancel = in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true);

        if (! OrderCancellationLimit::isWithinWindow($order, 'customer', 15)) {
            $minutes = OrderCancellationLimit::windowMinutesFor('customer', 15);

            return response()->json([
                'success' => false,
                'message' => "Customer cancellation window expired. Orders can only be cancelled within {$minutes} minutes of placement.",
            ], 422);
        }

        if (! $canInstantCancel && ! $canForceCancel) {
            return response()->json([
                'success' => false,
                'message' => 'This order can no longer be cancelled. Please contact support for further help.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            if ($canForceCancel && $order->payment_status === 'success') {
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
            } elseif ($order->payment_status === 'success') {
                $refundResult = $this->refundService->processRefund($order, $request->reason);

                if (! $refundResult['success']) {
                    throw new \Exception('Refund processing failed: ' . $refundResult['message']);
                }

                $message = 'Order cancelled and refund processed successfully!';
            } else {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->reason,
                ]);
                $message = 'Order cancelled successfully!';
            }

            DB::commit();

            $order = $order->fresh(['customer', 'restaurant', 'driver']);
            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your order #{$order->order_number} has been cancelled."
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'refund_amount' => $order->refund_amount ?? 0,
                    'refund_status' => $order->refund_status ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function myOrders(Request $request)
    {
        $orders = Order::where('customer_id', auth()->id())
            ->with(['restaurant', 'driver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $orders->getCollection()->transform(
            fn (Order $order) => $this->appendEtaToOrderPayload($order)
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function track($id)
    {
        $order = Order::with(['restaurant:id,name,address,latitude,longitude'])
            ->where('customer_id', auth()->id())
            ->select(
                'id',
                'order_number',
                'status',
                'order_type',
                'restaurant_id',
                'driver_id',
                'delivery_lat',
                'delivery_lng',
                'created_at'
            )
            ->findOrFail($id);

        $isTakeaway = ($order->order_type ?? 'delivery') === 'takeaway';
        $driverLocation = null;
        if (! $isTakeaway && $order->driver_id && in_array($order->status, ['picked_up', 'on_the_way'])) {
            $driverLocation = cache("driver_location_{$order->driver_id}");
        }

        $pickupLocation = null;
        if ($isTakeaway && $order->restaurant) {
            $pickupLocation = [
                'name' => $order->restaurant->name,
                'address' => $order->restaurant->address,
                'latitude' => $order->restaurant->latitude,
                'longitude' => $order->restaurant->longitude,
            ];
        }

        $eta = $isTakeaway ? null : $this->etaPayloadForOrder($order);

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'driver_location' => $driverLocation,
                'is_takeaway' => $isTakeaway,
                'pickup_location' => $pickupLocation,
                'status_text' => $this->getStatusText($order->status, $isTakeaway),
                'estimated_delivery_time' => $isTakeaway ? null : $this->getEstimatedDeliveryTime($order),
                'estimated_delivery_label' => $eta['eta_range'] ?? null,
                'estimated_delivery_minutes' => $eta['eta_minutes'] ?? null,
                'delivery_distance_km' => $eta['travel_distance_km'] ?? null,
                'eta' => $eta,
                'estimated_pickup_time' => $isTakeaway ? $this->getEstimatedPickupTime($order) : null,
            ],
        ]);
    }

    public function getRefundPolicy()
    {
        $policy = RefundPolicy::getActivePolicy();

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => 'Refund policy not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $policy->title,
                'content' => $policy->content,
                'refund_window_hours' => $policy->refund_window_hours,
                'cancellation_refund_rules' => $policy->cancellation_refund_rules,
            ],
        ]);
    }

    public function requestRefund(Request $request, $id)
    {
        $order = Order::where('customer_id', auth()->id())
            ->where('status', '!=', 'cancelled')
            ->whereNull('refund_status')
            ->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0.01|max:' . $order->total,
        ]);

        $policy = RefundPolicy::getActivePolicy();
        $orderAge = now()->diffInHours($order->created_at);

        if ($orderAge > $policy->refund_window_hours) {
            return response()->json([
                'success' => false,
                'message' => "Refund can only be requested within {$policy->refund_window_hours} hours of order placement",
            ], 400);
        }

        $order->update([
            'refund_reason' => $request->reason,
            'refund_status' => 'pending',
            'refund_amount' => $request->refund_amount ?? $order->refund_amount,
        ]);

        // Notify admin about refund request
        $this->notifyAdminRefundRequest($order);

        return response()->json([
            'success' => true,
            'message' => 'Refund request submitted successfully. Admin will review and process it shortly.',
        ]);
    }

    private function generateOrderNumber()
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $random = random_int(1000, 9999);
        $orderNumber = $prefix . $date . $random;

        while (Order::where('order_number', $orderNumber)->exists()) {
            $random = random_int(1000, 9999);
            $orderNumber = $prefix . $date . $random;
        }

        return $orderNumber;
    }

    private function calculateDeliveryFee($restaurantId, $lat, $lng, $subtotal = 0, string $orderType = 'delivery')
    {
        if ($orderType === 'takeaway') {
            return 0.0;
        }

        $restaurant = Restaurant::find($restaurantId);

        if (! $restaurant) {
            return (float) DeliveryChargeSetting::getDeliveryCharge();
        }

        $distance = null;
        if ($lat !== null && $lng !== null && $restaurant->latitude && $restaurant->longitude) {
            $distance = $this->calculateDistance(
                $restaurant->latitude,
                $restaurant->longitude,
                $lat,
                $lng
            );
        }

        $freeDeliveryThreshold = DeliveryChargeSetting::getFreeDeliveryThreshold(
            $restaurantId,
            $lat,
            $lng
        );
        if ($freeDeliveryThreshold !== null && (float) $subtotal >= (float) $freeDeliveryThreshold) {
            return 0.0;
        }

        return round((float) DeliveryChargeSetting::getDeliveryCharge($distance), 2);
    }

    private function buildPricingSummary(
        Restaurant $restaurant,
        float $subtotal,
        $deliveryLat = null,
        $deliveryLng = null,
        ?string $couponCode = null,
        string $orderType = 'delivery'
    ): array {
        $deliveryFee = $this->calculateDeliveryFee($restaurant->id, $deliveryLat, $deliveryLng, $subtotal, $orderType);
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

            if (! $promo || ! $promo->isValid()) {
                throw new \InvalidArgumentException('Invalid or expired coupon code');
            }

            if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
                throw new \InvalidArgumentException('Coupon usage limit exceeded');
            }

            if ($subtotal < $promo->min_order_amount) {
                throw new \InvalidArgumentException(
                    'Minimum order amount of ' . AppSetting::getValue('currency_symbol', '₹') . $promo->min_order_amount . ' required'
                );
            }

            if (method_exists($promo, 'isEligibleForUser') && ! $promo->isEligibleForUser(auth()->id())) {
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

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $kilometers = $miles * 1.609344;

        return round($kilometers, 2);
    }

    private function getStatusText($status, bool $isTakeaway = false)
    {
        if ($isTakeaway) {
            $takeawayStatuses = [
                'pending' => 'Order Placed',
                'confirmed' => 'Restaurant Confirmed',
                'preparing' => 'Preparing Your Food',
                'ready_for_pickup' => 'Ready to Collect',
                'picked_up' => 'Picked Up',
                'delivered' => 'Picked Up',
                'cancelled' => 'Cancelled',
            ];

            return $takeawayStatuses[$status] ?? $status;
        }

        $statuses = [
            'pending' => 'Order Placed',
            'confirmed' => 'Order Confirmed',
            'preparing' => 'Preparing Your Food',
            'ready_for_pickup' => 'Ready for Pickup',
            'picked_up' => 'Picked Up by Driver',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        return $statuses[$status] ?? $status;
    }

    private function getEstimatedDeliveryTime($order)
    {
        $eta = $this->etaPayloadForOrder($order);
        $minutes = $eta['eta_minutes'] ?? null;

        return $minutes ? $order->created_at->copy()->addMinutes($minutes) : null;
    }

    private function getEstimatedPickupTime($order)
    {
        $prepTime = (int) ($order->preparation_time_minutes
            ?? $order->restaurant?->order_lead_time
            ?? 20);

        return $order->created_at->addMinutes($prepTime);
    }

    private function getEstimatedDeliveryLabel($order): ?string
    {
        $eta = $this->etaPayloadForOrder($order);

        return $eta['eta_range'] ?? null;
    }

    private function getRefundPolicySummary()
    {
        $policy = RefundPolicy::getActivePolicy();

        if (! $policy) {
            return null;
        }

        return [
            'refund_window' => $policy->refund_window_hours . ' hours',
            'cancellation_rules' => $policy->cancellation_refund_rules ?? [],
        ];
    }

    private function notifyAdminRefundRequest($order)
    {
        Log::warning('Refund request submitted', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'refund_amount' => $order->refund_amount,
            'refund_reason' => $order->refund_reason,
        ]);
    }

    private function attachRefundPresentation(Order $order): void
    {
        if (! $order->refund_status && ! $order->refund_amount) {
            return;
        }

        $refundTransaction = Transaction::where('order_id', $order->id)
            ->where('type', 'refund')
            ->latest('id')
            ->first();

        $mode = strtolower((string) ($refundTransaction->payment_method ?? ''));
        if (! $mode && $order->refund_status) {
            $mode = $order->payment_method === 'cod' ? 'manual' : $order->payment_method;
        }

        $labels = [
            'wallet' => 'Customer wallet',
            'razorpay' => 'Razorpay',
            'stripe' => 'Stripe',
            'cashfree' => 'Cashfree',
            'paystack' => 'Paystack',
            'mollie' => 'Mollie',
            'mercadopago' => 'Mercado Pago',
            'cod' => 'Cash/manual adjustment',
            'manual' => 'Manual adjustment',
        ];

        $order->setAttribute('refund_mode', $mode ?: null);
        $order->setAttribute('refund_mode_label', $mode ? ($labels[$mode] ?? ucfirst($mode)) : null);
        $order->setAttribute('refund_transaction_id', $refundTransaction->transaction_id ?? $order->refund_transaction_id ?? null);
    }

    private function resolveSelectedOption(array $options, ?array $selected): ?array
    {
        if (! $selected || empty($selected['name'])) {
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

    private function appendEtaToOrderPayload(Order $order): array
    {
        $payload = $order->toArray();
        $eta = $this->etaPayloadForOrder($order);

        $payload['eta'] = $eta;
        $payload['delivery_distance_km'] = $eta['travel_distance_km'] ?? null;
        $payload['estimated_delivery_label'] = $eta['eta_range'] ?? null;
        $payload['estimated_delivery_minutes'] = $eta['eta_minutes'] ?? null;

        if (isset($payload['restaurant']) && is_array($payload['restaurant'])) {
            if (($eta['eta_minutes'] ?? null) !== null) {
                $payload['restaurant']['delivery_time'] = $eta['eta_minutes'];
            }
            $payload['restaurant']['logo_image'] = $this->resolveStorageUrl(
                $payload['restaurant']['logo_image'] ?? $payload['restaurant']['logo'] ?? null
            );
            $payload['restaurant']['logo'] = $payload['restaurant']['logo_image'];
            $payload['restaurant']['banner_image'] = $this->resolveStorageUrl(
                $payload['restaurant']['banner_image'] ?? $payload['restaurant']['image'] ?? null
            );
            $payload['restaurant']['eta_minutes'] = $eta['eta_minutes'];
            $payload['restaurant']['eta_range'] = $eta['eta_range'];
            $payload['restaurant']['travel_minutes'] = $eta['traffic_travel_minutes'];
            $payload['restaurant']['travel_distance_km'] = $eta['travel_distance_km'];
            $payload['restaurant']['preparation_minutes'] = $eta['preparation_minutes'];
        }

        return $payload;
    }

    private function resolveStorageUrl(?string $path): ?string
    {
        $value = trim((string) $path);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $normalized = ltrim($value, '/');
        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        return MediaStorage::url($normalized);
    }

    private function etaPayloadForOrder(Order $order): array
    {
        return app(GoogleMapsEtaService::class)->estimateDelivery(
            $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
            $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
            $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            (int) ($order->preparation_time_minutes ?? $order->restaurant?->order_lead_time ?? 20),
            $order->driver?->latitude !== null ? (float) $order->driver->latitude : null,
            $order->driver?->longitude !== null ? (float) $order->driver->longitude : null,
        );
    }
}
