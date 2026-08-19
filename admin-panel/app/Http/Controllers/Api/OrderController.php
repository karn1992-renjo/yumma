<?php

// app/Http/Controllers/Api/OrderController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\AppSetting;
use App\Models\DeliveryChargeSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderCancellationLimit;
use App\Models\OrderItem;
use App\Models\PaymentAttempt;
use App\Models\RefundPolicy;
use App\Models\Restaurant;
use App\Models\Review;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\DeliveryAreaResolver;
use App\Services\GoogleMapsEtaService;
use App\Services\MediaStorage;
use App\Services\OrderPaymentService;
use App\Services\OrderReleaseService;
use App\Services\OrderRewardPointService;
use App\Services\OrderStatusPushService;
use App\Services\PromotionEngineService;
use App\Services\PromotionRewardOrderItemService;
use App\Services\PromotionRewardSettlementService;
use App\Services\RefundService;
use App\Services\ScratchCardService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected $refundService;

    protected PromotionRewardOrderItemService $rewardOrderItemService;

    public function __construct(RefundService $refundService, PromotionRewardOrderItemService $rewardOrderItemService)
    {
        $this->refundService = $refundService;
        $this->rewardOrderItemService = $rewardOrderItemService;
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
            'payment_id' => 'nullable|string',
            'razorpay_order_id' => 'nullable|string',
            'razorpay_signature' => 'nullable|string',
            'stripe_payment_intent_id' => 'nullable|string',
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
        $transactionCommitted = false;

        try {
            $restaurant = Restaurant::find($request->restaurant_id);
            if (! $restaurant) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.',
                ], 404);
            }
            if (! $restaurant->isOpenNow()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant is currently closed. Orders cannot be placed until it reopens.',
                ], 400);
            }

            $orderType = strtolower($request->input('order_type', 'delivery'));
            if (! $restaurant->acceptsService($orderType)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $orderType === 'takeaway'
                        ? 'This restaurant is not accepting takeaway orders.'
                        : 'This restaurant is not accepting delivery orders.',
                ], 400);
            }

            if ($orderType === 'delivery' && ! $request->delivery_address_id && ! $request->filled('delivery_address')) {
                DB::rollBack();

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
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'image_url' => $menuItem->image_url ?? MediaStorage::url($menuItem->image),
                    'category_id' => $menuItem->category_id,
                    'brand_id' => $menuItem->brand_id ?? null,
                    'variant_id' => $variant['id'] ?? null,
                    'addon_ids' => collect($addOns)->pluck('id')->filter()->values()->all(),
                    'tags' => (array) ($menuItem->tags ?? []),
                    'food_type' => $menuItem->food_type ?? ($menuItem->is_veg ? 'veg' : 'non_veg'),
                    'price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'selected_variant' => $variant,
                    'selected_add_ons' => $addOns,
                    'promotion_id' => $item['promotion_id'] ?? null,
                    'promotion_title' => $item['promotion_title'] ?? null,
                    'promotion_group_key' => $item['promotion_group_key'] ?? null,
                    'promotion_group_size' => $item['promotion_group_size'] ?? null,
                    'promotion_deal_price' => $item['promotion_deal_price'] ?? null,
                    'promotion_original_price' => $item['promotion_original_price'] ?? null,
                    'line_type' => ! empty($item['promotion_id']) ? 'promotion_item' : 'paid',
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
                    $orderType,
                    $orderItems,
                    $request->payment_method
                );
            } catch (\InvalidArgumentException $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            $deliveryFee = $pricing['payable_delivery_fee'] ?? $pricing['delivery_fee'];
            $originalDeliveryFee = $pricing['original_delivery_fee'] ?? $pricing['delivery_fee'];
            $deliveryDiscount = $pricing['delivery_discount'] ?? max(0, $originalDeliveryFee - $deliveryFee);
            $tax = $pricing['tax'];
            $platformFee = $pricing['platform_fee'];
            $subtotal = $pricing['subtotal'] ?? $subtotal;
            $discount = $pricing['order_discount'] ?? $pricing['discount'];
            $total = $pricing['total'];
            $promo = $pricing['promo'];
            $orderItems = $this->rewardOrderItemService->applyRewardLines(
                $orderItems,
                $pricing['reward_lines'] ?? [],
                (int) $restaurant->id
            );
            $customerName = $request->customer_name ?: auth()->user()->name;
            $customerPhone = $request->customer_phone ?: auth()->user()->phone;
            if (preg_match('/[A-Za-z]/', (string) $customerPhone)) {
                DB::rollBack();

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
                DB::rollBack();

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

            $confirmedOnlinePayment = $request->attributes->get('confirmed_online_payment');
            $confirmedOnlinePayment = is_array($confirmedOnlinePayment) ? $confirmedOnlinePayment : null;
            $resolvedPaymentMethod = $confirmedOnlinePayment['payment_method'] ?? (in_array($request->payment_method, ['card', 'upi'], true)
                ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
                : $request->payment_method);
            $resolvedPaymentMethod = strtolower((string) $resolvedPaymentMethod);
            $verifiedOnlinePaymentId = $confirmedOnlinePayment['payment_id'] ?? null;
            if (! $confirmedOnlinePayment && in_array($resolvedPaymentMethod, ['razorpay', 'stripe', 'cashfree'], true)) {
                try {
                    $verifiedOnlinePaymentId = app(PaymentController::class)
                        ->verifyCheckoutPaymentForOrder($request, $resolvedPaymentMethod, (float) $total);
                } catch (\RuntimeException $e) {
                    if (DB::transactionLevel() > 0) {
                        DB::rollBack();
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }
            }

            $order = Order::create([
                'customer_id' => auth()->id(),
                'restaurant_id' => $request->restaurant_id,
                'order_type' => $orderType,
                'items' => $orderItems,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'original_delivery_fee' => $originalDeliveryFee,
                'delivery_discount' => $deliveryDiscount,
                'delivery_subsidy_source' => $pricing['delivery_subsidy_source'] ?? null,
                'admin_delivery_subsidy' => $pricing['admin_delivery_subsidy'] ?? 0,
                'restaurant_delivery_subsidy' => $pricing['restaurant_delivery_subsidy'] ?? 0,
                'platform_fee' => $platformFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'payment_method' => $resolvedPaymentMethod ?: $request->payment_method,
                'payment_gateway' => $verifiedOnlinePaymentId ? $resolvedPaymentMethod : null,
                'payment_source' => $verifiedOnlinePaymentId ? 'customer_app' : null,
                'delivery_payment_mode' => $request->payment_method === 'cod'
                    ? 'cod'
                    : 'online',
                'payment_status' => $verifiedOnlinePaymentId ? 'success' : 'pending',
                'payment_id' => $verifiedOnlinePaymentId,
                'paid_at' => $verifiedOnlinePaymentId ? now() : null,
                'online_payment_verified_at' => $verifiedOnlinePaymentId ? now() : null,
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
            } elseif ($verifiedOnlinePaymentId) {
                Transaction::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'transaction_id' => $verifiedOnlinePaymentId,
                    ],
                    [
                        'user_id' => auth()->id(),
                        'amount' => $order->total,
                        'type' => 'payment',
                        'status' => 'success',
                        'payment_method' => $resolvedPaymentMethod,
                    ]
                );
                if ($confirmedOnlinePayment) {
                    PaymentAttempt::create([
                        'order_id' => $order->id,
                        'gateway' => $confirmedOnlinePayment['payment_method'],
                        'source' => PaymentAttempt::SOURCE_CUSTOMER_APP,
                        'status' => PaymentAttempt::STATUS_SUCCESS,
                        'amount' => $order->total,
                        'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                        'transaction_id' => $verifiedOnlinePaymentId,
                        'gateway_reference' => $confirmedOnlinePayment['gateway_reference'] ?? null,
                        'payment_link_id' => $confirmedOnlinePayment['gateway_reference'] ?? null,
                        'created_by' => auth()->id(),
                        'gateway_payload' => $confirmedOnlinePayment['payload'] ?? null,
                        'paid_at' => now(),
                        'idempotency_key' => 'checkout_paid_' . $verifiedOnlinePaymentId,
                    ]);
                    $order->increment('payment_attempts_count');
                }
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
                    'special_instructions' => $item['special_instructions'] ?? null,
                ]);
            }

            app(PromotionEngineService::class)->recordUsage(
                $order,
                $pricing['promotion_result'] ?? [],
                $pricing['promotion_context'] ?? []
            );

            DB::commit();
            $transactionCommitted = true;

            // Release the order before any non-essential enrichment or customer
            // notification work so the restaurant receives it immediately.
            try {
                $releasedToRestaurant = app(OrderReleaseService::class)->releaseToRestaurant($order);
            } catch (\Throwable $e) {
                $releasedToRestaurant = false;
                Log::warning('Order restaurant release failed after placement.', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->warmConfirmedRouteEta($order->fresh(['restaurant', 'driver']));

            if ($order->payment_status === 'success') {
                try {
                    app(OrderStatusPushService::class)->notifyCustomer(
                        $order->fresh(['customer', 'restaurant']),
                        "Payment confirmed. Your order #{$order->order_number} has been placed successfully."
                    );
                } catch (\Throwable $e) {
                    Log::warning('Customer payment confirmation notification failed.', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $scratchCardService = app(ScratchCardService::class);
            $issuedScratchCards = [];
            if ($releasedToRestaurant) {
                $releasedOrder = $order->fresh(['customer']);
                try {
                    $issuedScratchCards = $scratchCardService->issueForRecordedUsage($releasedOrder, 'payment_success');
                    app(PromotionRewardSettlementService::class)->settleForOrder($releasedOrder, 'order_released');
                } catch (\Throwable $e) {
                    Log::warning('Order post-release reward settlement failed.', [
                        'order_id' => $order->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $requiresPayment = ! $releasedToRestaurant
                && ! $order->isCashOnDelivery()
                && $order->payment_status !== 'success';

            return response()->json([
                'success' => true,
                'message' => $requiresPayment
                    ? 'Payment pending. Complete payment to place the order.'
                    : 'Order placed successfully',
                'data' => [
                    'order' => $order,
                    'order_number' => $order->order_number,
                    'total' => $total,
                    'platform_fee' => $platformFee,
                    'scratch_cards' => collect($issuedScratchCards)
                        ->map(fn ($card) => $scratchCardService->payload($card))
                        ->values(),
                    'requires_payment' => $requiresPayment,
                    'reward_points_earned' => (int) floor((float) $order->total),
                    'reward_points_balance' => (int) (auth()->user()?->reward_points_balance ?? 0),
                    'reward_points_message' => 'You will earn ' . ((int) floor((float) $order->total)) . ' points after delivery.',
                    'refund_policy' => $this->getRefundPolicySummary(),
                ],
            ], 201);
        } catch (\Exception $e) {
            if (! $transactionCommitted) {
                DB::rollback();
            }

            return response()->json([
                'success' => false,
                'message' => $e instanceof \InvalidArgumentException
                    ? $e->getMessage()
                    : 'Failed to place order: ' . $e->getMessage(),
            ], $e instanceof \InvalidArgumentException ? 409 : 500);
        }
    }

    public function show($id)
    {
        $order = Order::with([
            'restaurant',
            'driver' => function ($query) {
                $query->withCount(['orders as delivered_orders_count' => function ($orderQuery) {
                    $orderQuery->where('status', 'delivered');
                }]);
            },
        ])
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

    public function priceCheckoutPayload(Request $request, ?string $paymentMethod = null): array
    {
        $restaurant = Restaurant::find($request->restaurant_id);
        if (! $restaurant) {
            throw new \InvalidArgumentException('Restaurant not found.');
        }
        if (! $restaurant->isOpenNow()) {
            throw new \InvalidArgumentException('Restaurant is currently closed. Orders cannot be placed until it reopens.');
        }

        $orderType = strtolower($request->input('order_type', 'delivery'));
        if (! $restaurant->acceptsService($orderType)) {
            throw new \InvalidArgumentException($orderType === 'takeaway'
                ? 'This restaurant is not accepting takeaway orders.'
                : 'This restaurant is not accepting delivery orders.');
        }
        if ($orderType === 'delivery' && ! $request->delivery_address_id && ! $request->filled('delivery_address')) {
            throw new \InvalidArgumentException('Please select or enter a delivery address.');
        }

        $subtotal = 0;
        $items = [];
        foreach ($request->items as $item) {
            $menuItem = MenuItem::find($item['id']);
            if (! $menuItem) {
                throw new \InvalidArgumentException('Menu item not found.');
            }

            $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
            $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
            $unitPrice = $menuItem->getFinalPriceAttribute()
                + ($variant['price'] ?? 0)
                + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));
            $subtotal += $unitPrice * $item['quantity'];

            $items[] = [
                'id' => $menuItem->id,
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'image_url' => $menuItem->image_url ?? MediaStorage::url($menuItem->image),
                'category_id' => $menuItem->category_id,
                'brand_id' => $menuItem->brand_id ?? null,
                'variant_id' => $variant['id'] ?? null,
                'addon_ids' => collect($addOns)->pluck('id')->filter()->values()->all(),
                'tags' => (array) ($menuItem->tags ?? []),
                'food_type' => $menuItem->food_type ?? ($menuItem->is_veg ? 'veg' : 'non_veg'),
                'price' => $unitPrice,
                'quantity' => $item['quantity'],
                'selected_variant' => $variant,
                'selected_add_ons' => $addOns,
                'promotion_id' => $item['promotion_id'] ?? null,
                'promotion_title' => $item['promotion_title'] ?? null,
                'promotion_group_key' => $item['promotion_group_key'] ?? null,
                'promotion_group_size' => $item['promotion_group_size'] ?? null,
                'promotion_deal_price' => $item['promotion_deal_price'] ?? null,
                'promotion_original_price' => $item['promotion_original_price'] ?? null,
                'line_type' => ! empty($item['promotion_id']) ? 'promotion_item' : 'paid',
            ];
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

        $pricing = $this->buildPricingSummary(
            $restaurant,
            $subtotal,
            $deliveryLat,
            $deliveryLng,
            $request->coupon_code,
            $orderType,
            $items,
            $paymentMethod
        );

        return [
            'restaurant' => $restaurant,
            'subtotal' => $subtotal,
            'items' => $items,
            'pricing' => $pricing,
            'order_type' => $orderType,
            'delivery_lat' => $deliveryLat,
            'delivery_lng' => $deliveryLng,
        ];
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
        $summaryItems = [];
        foreach ($request->items as $item) {
            $menuItem = MenuItem::find($item['id']);
            $variant = $this->resolveSelectedOption($menuItem->variants ?? [], $item['selected_variant'] ?? null);
            $addOns = $this->resolveSelectedAddOns($menuItem->add_ons ?? [], $item['selected_add_ons'] ?? []);
            $unitPrice = $menuItem->getFinalPriceAttribute()
                + ($variant['price'] ?? 0)
                + collect($addOns)->sum(fn ($addOn) => (float) ($addOn['price'] ?? 0));

            $subtotal += $unitPrice * $item['quantity'];
            $summaryItems[] = [
                'id' => $menuItem->id,
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'image_url' => $menuItem->image_url ?? MediaStorage::url($menuItem->image),
                'category_id' => $menuItem->category_id,
                'brand_id' => $menuItem->brand_id ?? null,
                'variant_id' => $variant['id'] ?? null,
                'addon_ids' => collect($addOns)->pluck('id')->filter()->values()->all(),
                'tags' => (array) ($menuItem->tags ?? []),
                'food_type' => $menuItem->food_type ?? ($menuItem->is_veg ? 'veg' : 'non_veg'),
                'price' => $unitPrice,
                'quantity' => $item['quantity'],
                'selected_variant' => $variant,
                'selected_add_ons' => $addOns,
                'promotion_id' => $item['promotion_id'] ?? null,
                'promotion_title' => $item['promotion_title'] ?? null,
                'promotion_group_key' => $item['promotion_group_key'] ?? null,
                'promotion_group_size' => $item['promotion_group_size'] ?? null,
                'promotion_deal_price' => $item['promotion_deal_price'] ?? null,
                'promotion_original_price' => $item['promotion_original_price'] ?? null,
                'line_type' => ! empty($item['promotion_id']) ? 'promotion_item' : 'paid',
            ];
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
                $orderType,
                $summaryItems
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        unset($pricing['promo'], $pricing['promotion_result'], $pricing['promotion_context']);
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
            'items' => 'nullable|array',
            'items.*.menu_item_id' => 'required_with:items|integer',
            'items.*.rating' => 'required_with:items|integer|min:1|max:5',
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

            $itemRatings = $validated['items'] ?? [];
            if (! empty($itemRatings)) {
                $orderItemsPayload = $order->items ?? [];

                foreach ($itemRatings as $itemInput) {
                    $menuItemId = (int) $itemInput['menu_item_id'];
                    $rating = (int) $itemInput['rating'];

                    OrderItem::where('order_id', $order->id)
                        ->where('menu_item_id', $menuItemId)
                        ->update(['rating' => $rating]);

                    foreach ($orderItemsPayload as &$itemRow) {
                        $rowMenuItemId = (int) ($itemRow['menu_item_id'] ?? $itemRow['id'] ?? 0);
                        if ($rowMenuItemId === $menuItemId) {
                            $itemRow['rating'] = $rating;
                        }
                    }
                    unset($itemRow);

                    $menuRatingSummary = OrderItem::where('menu_item_id', $menuItemId)
                        ->whereNotNull('rating')
                        ->selectRaw('AVG(rating) as average_rating, COUNT(*) as total_ratings')
                        ->first();

                    if ($menuRatingSummary) {
                        MenuItem::where('id', $menuItemId)->update([
                            'rating' => round((float) $menuRatingSummary->average_rating, 1),
                            'total_ratings' => (int) $menuRatingSummary->total_ratings,
                        ]);
                    }
                }

                $order->update(['items' => $orderItemsPayload]);
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

    /**
     * Tip the delivery partner for a delivered order. The full amount is
     * credited to the driver's wallet with no commission deducted.
     */
    public function tip(Request $request, $id)
    {
        $order = Order::where('customer_id', auth()->id())->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'You can tip your delivery partner only after the order is delivered.',
            ], 422);
        }

        if (! $order->driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'This order does not have a delivery partner to tip.',
            ], 422);
        }

        if ((float) ($order->tip_amount ?? 0) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'You have already tipped your delivery partner for this order.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:500',
        ]);
        $amount = (float) $validated['amount'];

        DB::beginTransaction();

        try {
            $order = Order::where('customer_id', auth()->id())->lockForUpdate()->findOrFail($id);

            if ((float) ($order->tip_amount ?? 0) > 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'You have already tipped your delivery partner for this order.',
                ], 422);
            }

            $customerWallet = Wallet::where('user_id', auth()->id())->lockForUpdate()->first();
            if (! $customerWallet || $customerWallet->balance < $amount) {
                DB::rollBack();

                $shortfall = round($amount - (float) ($customerWallet->balance ?? 0), 2);

                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance to add this tip.',
                    'data' => ['shortfall' => max(0, $shortfall)],
                ], 422);
            }

            $customerWallet->decrement('balance', $amount);
            $customerWallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $customerWallet->id,
                'user_id' => auth()->id(),
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $customerWallet->balance,
                'reference_type' => 'order_tip',
                'reference_id' => $order->id,
                'description' => "Tip for order #{$order->order_number}",
            ]);

            $driverWallet = Wallet::where('user_id', $order->driver_id)->lockForUpdate()->first()
                ?: Wallet::create([
                    'user_id' => $order->driver_id,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'currency' => 'INR',
                    'is_active' => true,
                ]);

            // Full amount credited — deliberately not passed through
            // CommissionSetting::calculate(), unlike delivery-fee earnings.
            $driverWallet->increment('balance', $amount);
            $driverWallet->refresh();

            WalletTransaction::create([
                'wallet_id' => $driverWallet->id,
                'user_id' => $order->driver_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $driverWallet->balance,
                'reference_type' => 'driver_tip_earning',
                'reference_id' => $order->id,
                'description' => "Tip from customer for order #{$order->order_number}",
                'meta' => ['source' => 'tip'],
            ]);

            $order->update([
                'tip_amount' => $amount,
                'tip_paid_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thanks! Your tip has been sent to your delivery partner.',
                'data' => $order->fresh(['restaurant', 'driver']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order tip error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process tip: ' . $e->getMessage(),
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
                "Your order #{$order->order_number} has been cancelled.",
                ['customer', 'restaurant', 'driver'],
                'customer'
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
        $order = Order::with(['restaurant:id,name,address,latitude,longitude,order_lead_time', 'driver:id,latitude,longitude,updated_at'])
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
                'confirmed_at',
                'preparation_time_minutes',
                'ready_at',
                'created_at'
            )
            ->findOrFail($id);

        $isTakeaway = ($order->order_type ?? 'delivery') === 'takeaway';
        $driverLocation = $isTakeaway ? null : $this->driverLocationPayload($order);

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
                'preparation' => $order->preparationTimingPayload(),
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

    private function calculateChargeableDeliveryFee($restaurantId, $lat, $lng, string $orderType = 'delivery'): float
    {
        if ($orderType === 'takeaway') {
            return 0.0;
        }

        $restaurant = Restaurant::find($restaurantId);
        if (! $restaurant) {
            return round((float) DeliveryChargeSetting::getDeliveryCharge(), 2);
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

        return round((float) DeliveryChargeSetting::getDeliveryCharge($distance), 2);
    }

    private function buildPricingSummary(
        Restaurant $restaurant,
        float $subtotal,
        $deliveryLat = null,
        $deliveryLng = null,
        ?string $couponCode = null,
        string $orderType = 'delivery',
        array $items = [],
        ?string $paymentMethod = null
    ): array {
        $originalDeliveryFee = round(
            $this->calculateChargeableDeliveryFee($restaurant->id, $deliveryLat, $deliveryLng, $orderType),
            2
        );
        $platformFee = round(max(0, (float) DeliveryChargeSetting::getPlatformFee()), 2);

        $activeTaxes = TaxSetting::getActiveTaxes();
        if ($activeTaxes->isEmpty()) {
            $tax = 0.0;
            $taxRate = 0.0;
            $taxLabel = 'Taxes & charges';
            $taxBreakdown = [];
        } else {
            $taxRate = (float) $activeTaxes->sum('rate');
            $taxBreakdown = TaxSetting::calculateBreakdown($subtotal, $originalDeliveryFee);
            $tax = round((float) collect($taxBreakdown)->sum('amount'), 2);
            $taxLabel = 'Taxes & charges';
        }
        $baseTax = $tax;
        $baseTaxBreakdown = $taxBreakdown;
        $packagingFee = round((float) collect($taxBreakdown)
            ->where('type', 'packaging_charge')
            ->sum('amount'), 2);

        $discount = 0.0;
        $promo = null;

        $deliveryDistanceKm = null;
        if ($orderType !== 'takeaway'
            && $deliveryLat !== null
            && $deliveryLng !== null
            && $restaurant->latitude !== null
            && $restaurant->longitude !== null) {
            $deliveryDistanceKm = $this->calculateDistance(
                (float) $restaurant->latitude,
                (float) $restaurant->longitude,
                (float) $deliveryLat,
                (float) $deliveryLng
            );
        }
        $deliveryArea = app(DeliveryAreaResolver::class)->resolve(
            $deliveryLat !== null ? (float) $deliveryLat : null,
            $deliveryLng !== null ? (float) $deliveryLng : null
        );

        $promotionContext = [
            'user_id' => auth()->id(),
            'restaurant_id' => $restaurant->id,
            'zone_id' => $deliveryArea?->id,
            'subtotal' => $subtotal,
            'delivery_fee' => $originalDeliveryFee,
            'platform_fee' => $platformFee,
            'packaging_fee' => $packagingFee,
            // Packaging charges are already part of the tax breakdown. Split
            // them here because the promotion engine models that bucket
            // independently and would otherwise count it twice.
            'tax' => max(0, round($tax - $packagingFee, 2)),
            'distance_km' => $deliveryDistanceKm,
            'coupon_code' => $couponCode,
            'order_type' => $orderType,
            'payment_method' => $paymentMethod,
            'items' => $items,
        ];
        $promotionResult = app(PromotionEngineService::class)->calculate($promotionContext);

        if ($couponCode && ! collect($promotionResult['discount_lines'] ?? [])->contains(fn (array $line) => ! empty($line['coupon_code']))) {
            $reason = collect($promotionResult['invalid_reasons'] ?? [])
                ->first(fn (array $reason) => strtoupper((string) ($reason['coupon_code'] ?? '')) === strtoupper($couponCode));

            throw new \InvalidArgumentException($reason['reason'] ?? 'Invalid or expired coupon code');
        }

        $itemSubtotalFloor = $this->promotionItemSubtotalFloor($items, $subtotal);
        $embeddedDiscount = $this->embeddedSubtotalDiscount($promotionResult, $subtotal, $itemSubtotalFloor);
        $billableSubtotal = max(0, round($subtotal - $embeddedDiscount, AppSetting::currencyDecimals()));

        $thresholdDeliveryDiscount = $this->thresholdDeliveryDiscount(
            $restaurant->id,
            $deliveryLat,
            $deliveryLng,
            $billableSubtotal,
            $originalDeliveryFee,
            $orderType
        );
        $promotionDeliveryDiscount = min(
            $originalDeliveryFee,
            round((float) ($promotionResult['delivery_discount'] ?? 0), AppSetting::currencyDecimals())
        );
        $deliveryDiscount = min($originalDeliveryFee, max($thresholdDeliveryDiscount, $promotionDeliveryDiscount));
        $payableDeliveryFee = max(0, round($originalDeliveryFee - $deliveryDiscount, AppSetting::currencyDecimals()));
        $deliverySubsidy = $this->deliverySubsidyBreakdown(
            $deliveryDiscount
        );

        $orderDiscount = round(
            max(0, (float) ($promotionResult['discount'] ?? 0) - $embeddedDiscount - $promotionDeliveryDiscount),
            AppSetting::currencyDecimals()
        );
        $displayDiscount = round($orderDiscount + $deliveryDiscount, AppSetting::currencyDecimals());
        $billDiscount = round($displayDiscount + $embeddedDiscount, AppSetting::currencyDecimals());

        if ($activeTaxes->isNotEmpty()) {
            // Free-delivery coupons subsidize only the customer delivery line.
            // Platform fee and taxable charge bases remain chargeable; item-level
            // promotions may lower the item subtotal base, but delivery tax is
            // still based on the original chargeable delivery fee.
            $taxableSubtotal = $embeddedDiscount > 0 ? $billableSubtotal : $subtotal;
            $taxBreakdown = TaxSetting::calculateBreakdown($taxableSubtotal, $originalDeliveryFee);
            $tax = round((float) collect($taxBreakdown)->sum('amount'), 2);
            if ($tax <= 0 && $baseTax > 0 && $embeddedDiscount <= 0) {
                $tax = $baseTax;
                $taxBreakdown = $baseTaxBreakdown;
            }
        }
        $promo = $promotionResult['promo'] ?? null;
        $total = max(0, round($billableSubtotal + $payableDeliveryFee + $platformFee + $tax - $orderDiscount, 2));
        $orderEarnedPoints = (int) floor($total);

        return [
            'delivery_fee' => round($originalDeliveryFee, 2),
            'payable_delivery_fee' => round($payableDeliveryFee, 2),
            'customer_delivery_fee' => round($payableDeliveryFee, 2),
            'original_delivery_fee' => round($originalDeliveryFee, 2),
            'delivery_discount' => round($deliveryDiscount, 2),
            'delivery_subsidy_source' => $deliverySubsidy['source'],
            'admin_delivery_subsidy' => round($deliverySubsidy['admin'], 2),
            'restaurant_delivery_subsidy' => round($deliverySubsidy['restaurant'], 2),
            'order_type' => $orderType,
            'subtotal' => round($billableSubtotal, 2),
            'original_subtotal' => round($subtotal, 2),
            'embedded_item_discount' => round($embeddedDiscount, 2),
            'platform_fee' => $platformFee,
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'tax_label' => $taxLabel,
            'tax_breakdown' => $taxBreakdown,
            'discount' => $displayDiscount,
            'bill_discount' => $billDiscount,
            'total_discount' => $billDiscount,
            'order_discount' => $orderDiscount,
            'promotion_discount' => round((float) ($promotionResult['promotion_discount'] ?? 0), 2),
            'item_discount' => round((float) ($promotionResult['item_discount'] ?? 0), 2),
            'coupon_discount' => round((float) ($promotionResult['coupon_discount'] ?? 0), 2),
            'packaging_discount' => round((float) ($promotionResult['packaging_discount'] ?? 0), 2),
            'cashback_earned' => round((float) ($promotionResult['cashback_earned'] ?? 0), 2),
            'reward_points_earned' => $orderEarnedPoints,
            'promotion_reward_points_earned' => (int) ($promotionResult['reward_points_earned'] ?? 0),
            'reward_points_balance' => (int) (auth()->user()?->reward_points_balance ?? 0),
            'reward_points_message' => $orderEarnedPoints > 0 ? 'You will earn ' . $orderEarnedPoints . ' points after delivery.' : null,
            'gift_voucher_amount' => round((float) ($promotionResult['gift_voucher_amount'] ?? 0), 2),
            'reward_lines' => $promotionResult['reward_lines'] ?? [],
            'free_items' => $promotionResult['free_items'] ?? [],
            'promotion_progress' => $promotionResult['promotion_progress'] ?? [],
            'reward_actions' => $promotionResult['reward_actions'] ?? [],
            'reward_candidates' => $promotionResult['reward_candidates'] ?? [],
            'applied_promotions' => $promotionResult['applied_promotions'] ?? [],
            'eligible_promotions' => $promotionResult['eligible_promotions'] ?? [],
            'invalid_reasons' => $promotionResult['invalid_reasons'] ?? [],
            'discount_lines' => $promotionResult['discount_lines'] ?? [],
            'promo_liability_lines' => $promotionResult['promo_liability_lines'] ?? [],
            'funding_breakdown' => $promotionResult['funding_breakdown'] ?? [],
            'budget_remaining' => $promotionResult['budget_remaining'] ?? null,
            'offer_applicability' => $promotionResult['offer_applicability'] ?? [],
            'total' => $total,
            'promo' => $promo,
            'promotion_result' => $promotionResult,
            'promotion_context' => $promotionContext,
        ];
    }

    private function promotionItemSubtotalFloor(array $items, float $subtotal): float
    {
        $floor = 0.0;
        $grouped = [];

        foreach ($items as $index => $item) {
            $dealPrice = isset($item['promotion_deal_price'])
                ? (float) $item['promotion_deal_price']
                : 0.0;
            $promotionId = $item['promotion_id'] ?? null;
            if ($dealPrice <= 0 || empty($promotionId)) {
                $floor += ((float) ($item['price'] ?? 0)) * max(1, (int) ($item['quantity'] ?? 1));

                continue;
            }

            $groupKey = trim((string) ($item['promotion_group_key'] ?? ''));
            $key = implode(':', [
                (string) $promotionId,
                $groupKey !== '' ? $groupKey : 'group',
                number_format($dealPrice, 2, '.', ''),
            ]);

            $grouped[$key]['deal_price'] = $dealPrice;
            $groupSize = isset($item['promotion_group_size'])
                ? (int) $item['promotion_group_size']
                : 0;
            if ($groupSize > 0) {
                $grouped[$key]['expected_size'] = max((int) ($grouped[$key]['expected_size'] ?? 0), $groupSize);
            }
            $grouped[$key]['lines'][] = [
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => (float) ($item['price'] ?? 0),
            ];
        }

        foreach ($grouped as $group) {
            $lines = $group['lines'] ?? [];
            $expectedSize = (int) ($group['expected_size'] ?? 0);
            if ($expectedSize <= 0) {
                $expectedSize = count($lines) > 1 ? count($lines) : 2;
            }

            if (count($lines) < $expectedSize) {
                foreach ($lines as $line) {
                    $floor += ((float) ($line['price'] ?? 0)) * max(1, (int) ($line['quantity'] ?? 1));
                }

                continue;
            }

            $sets = min(array_map(fn (array $line) => max(1, (int) ($line['quantity'] ?? 1)), $lines));
            $sets = max(1, (int) $sets);
            $floor += ((float) $group['deal_price']) * $sets;

            foreach ($lines as $line) {
                $extraQuantity = max(1, (int) ($line['quantity'] ?? 1)) - $sets;
                if ($extraQuantity > 0) {
                    $floor += ((float) ($line['price'] ?? 0)) * $extraQuantity;
                }
            }
        }

        return round($floor, AppSetting::currencyDecimals());
    }

    private function embeddedSubtotalDiscount(array $promotionResult, float $subtotal, float $itemSubtotalFloor): float
    {
        $floorDiscount = $itemSubtotalFloor > 0 && $itemSubtotalFloor < $subtotal
            ? $subtotal - $itemSubtotalFloor
            : 0.0;

        $lineDiscount = collect($promotionResult['discount_lines'] ?? [])
            ->filter(fn (array $line) => $this->shouldEmbedPromotionLineInSubtotal($line))
            ->sum(fn (array $line) => (float) ($line['discount_amount'] ?? 0));

        return round(min($subtotal, max($floorDiscount, (float) $lineDiscount)), AppSetting::currencyDecimals());
    }

    private function shouldEmbedPromotionLineInSubtotal(array $line): bool
    {
        $type = strtolower(str_replace('-', '_', (string) ($line['type'] ?? '')));

        return ($line['bucket'] ?? null) === 'item_discount'
            && in_array($type, [
                'bogo',
                'buy_1_get_1',
                'buy_2_get_1',
                'buy_3_get_1',
                'buy_3_get_2',
                'buy_x_get_y',
                'buy_x_get_x',
                'free_quantity',
                'combo_deal',
                'meal_deal',
            ], true);
    }

    private function thresholdDeliveryDiscount(
        int $restaurantId,
        $deliveryLat,
        $deliveryLng,
        float $subtotal,
        float $originalDeliveryFee,
        string $orderType
    ): float {
        if ($orderType === 'takeaway' || $originalDeliveryFee <= 0) {
            return 0.0;
        }

        $threshold = DeliveryChargeSetting::getFreeDeliveryThreshold(
            $restaurantId,
            $deliveryLat,
            $deliveryLng
        );

        if ($threshold === null || $subtotal < (float) $threshold) {
            return 0.0;
        }

        return round($originalDeliveryFee, AppSetting::currencyDecimals());
    }

    private function deliverySubsidyBreakdown(float $deliveryDiscount): array
    {
        if ($deliveryDiscount <= 0) {
            return ['source' => null, 'admin' => 0.0, 'restaurant' => 0.0];
        }

        $setting = DeliveryChargeSetting::first();

        return $this->splitDeliverySubsidy(
            $deliveryDiscount,
            (float) ($setting?->admin_contribution_percent ?? 100),
            (float) ($setting?->restaurant_contribution_percent ?? 0),
            'shared'
        );
    }

    private function splitDeliverySubsidy(
        float $amount,
        float $adminPercent,
        float $restaurantPercent,
        string $source
    ): array {
        $totalPercent = $adminPercent + $restaurantPercent;
        if ($totalPercent <= 0) {
            return ['source' => 'admin', 'admin' => round($amount, 2), 'restaurant' => 0.0];
        }

        $admin = round($amount * ($adminPercent / $totalPercent), 2);
        $restaurant = round(max(0, $amount - $admin), 2);

        return [
            'source' => $source,
            'admin' => $admin,
            'restaurant' => $restaurant,
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
        $driverLocation = $this->driverLocationPayload($order);
        $eta = $this->etaPayloadForOrder($order);

        $payload['eta'] = $eta;
        $payload['driver_location'] = $driverLocation;
        $payload['items'] = $this->appendItemImages($payload['items'] ?? []);
        $payload = array_merge($payload, $order->preparationTimingPayload());
        $payload['delivery_distance_km'] = $eta['travel_distance_km'] ?? null;
        $payload['estimated_delivery_label'] = $eta['eta_range'] ?? null;
        $payload['estimated_delivery_minutes'] = $eta['eta_minutes'] ?? null;
        $payload['payment_summary'] = app(OrderPaymentService::class)->statusPayload($order);
        $payload['active_payment_attempt'] = $payload['payment_summary']['active_attempt'] ?? null;
        $orderPoints = $order->status === 'delivered'
            ? app(OrderRewardPointService::class)->earnedPointsForOrder($order)
            : (int) floor(max(0, (float) $order->total));
        $payload['reward_points_earned'] = $orderPoints;
        $payload['reward_points_balance'] = (int) ($order->customer?->reward_points_balance ?? auth()->user()?->reward_points_balance ?? 0);
        $payload['reward_points_message'] = $orderPoints > 0
            ? ($order->status === 'delivered'
                ? 'You earned ' . $orderPoints . ' points for this order.'
                : 'You will earn ' . $orderPoints . ' points after delivery.')
            : null;

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

    private function appendItemImages($items): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($items)) {
            return [];
        }

        $menuItemIds = collect($items)
            ->map(fn ($item) => (int) ($item['menu_item_id'] ?? $item['id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($menuItemIds->isEmpty()) {
            return array_values($items);
        }

        $images = MenuItem::whereIn('id', $menuItemIds)
            ->get(['id', 'images'])
            ->mapWithKeys(fn (MenuItem $item) => [
                $item->id => $item->image_url ?? MediaStorage::url($item->image),
            ]);

        return collect($items)
            ->map(function ($item) use ($images) {
                if (! is_array($item)) {
                    return $item;
                }

                $hasImage = filled($item['image_url'] ?? null) || filled($item['image'] ?? null);
                if (! $hasImage) {
                    $menuItemId = (int) ($item['menu_item_id'] ?? $item['id'] ?? 0);
                    $imageUrl = $images->get($menuItemId);
                    if (filled($imageUrl)) {
                        $item['image_url'] = $imageUrl;
                    }
                }

                return $item;
            })
            ->values()
            ->all();
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

    private function driverLocationPayload(Order $order): ?array
    {
        if (! $order->driver_id) {
            return null;
        }

        $cached = cache("driver_location_{$order->driver_id}", []);
        $lat = is_array($cached) ? ($cached['lat'] ?? null) : null;
        $lng = is_array($cached) ? ($cached['lng'] ?? null) : null;
        $lat ??= $order->driver?->latitude;
        $lng ??= $order->driver?->longitude;

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'updated_at' => is_array($cached) && isset($cached['updated_at'])
                ? (string) $cached['updated_at']
                : $order->driver?->updated_at?->toIso8601String(),
        ];
    }

    private function etaPayloadForOrder(Order $order): array
    {
        $driverLocation = $this->driverLocationPayload($order);

        return app(GoogleMapsEtaService::class)->estimateDelivery(
            $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
            $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
            $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            $order->remainingPreparationMinutes(),
            $driverLocation['lat'] ?? ($order->driver?->latitude !== null ? (float) $order->driver->latitude : null),
            $driverLocation['lng'] ?? ($order->driver?->longitude !== null ? (float) $order->driver->longitude : null),
        );
    }

    private function warmConfirmedRouteEta(Order $order): void
    {
        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return;
        }

        try {
            app(GoogleMapsEtaService::class)->estimateDelivery(
                $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
                $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
                $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
                $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
                (int) ($order->preparation_time_minutes ?? $order->restaurant?->order_lead_time ?? 20),
                null,
                null,
                true
            );
        } catch (\Throwable $exception) {
            Log::warning('Confirmed order route ETA warmup failed: ' . $exception->getMessage(), [
                'order_id' => $order->id,
            ]);
        }
    }
}
