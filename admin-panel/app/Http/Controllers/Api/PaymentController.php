<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderEvent;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Transaction;
use App\Services\OrderReleaseService;
use App\Services\OrderStatusPushService;
use App\Services\PromotionRewardSettlementService;
use App\Services\ScratchCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:razorpay,stripe,cashfree,card,upi',
        ]);

        $order = Order::where('customer_id', auth()->id())
            ->where('payment_status', 'pending')
            ->findOrFail($request->order_id);

        $paymentMethod = in_array($request->payment_method, ['card', 'upi'])
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : $request->payment_method;

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));

            if (! $key || ! $secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.',
                ], 503);
            }

            $orderData = [
                'receipt' => 'order_' . $order->order_number,
                'amount' => (int) round($order->total * 100),
                'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                'payment_capture' => 1,
            ];

            $razorpayOrder = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', $orderData);

            if (! $razorpayOrder->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create Razorpay order.',
                ], 502);
            }

            $razorpayOrder = $razorpayOrder->json();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => 'razorpay',
                    'order_id' => $razorpayOrder['id'],
                    'amount' => $razorpayOrder['amount'],
                    'currency' => $razorpayOrder['currency'],
                    'key' => $key,
                ],
            ]);
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            $stripeKey = AppSetting::getValue('stripe_key', config('services.stripe.key'));

            if (! $stripeSecret || ! $stripeKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.',
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);

            $currency = strtolower(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($order->total * 100),
                'currency' => $currency,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => 'stripe',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $stripeKey,
                ],
            ]);
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (! $clientId || ! $clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.',
                ], 503);
            }

            try {
                $cashfreeOrder = Http::withHeaders([
                    'x-api-version' => $apiVersion,
                    'x-client-id' => $clientId,
                    'x-client-secret' => $clientSecret,
                ])->post($this->cashfreeBaseUrl() . '/pg/orders', [
                    'order_id' => 'ORDER_' . $order->id . '_' . time(),
                    'order_amount' => round($order->total, 2),
                    'order_currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                    'order_note' => 'Payment to ' . AppSetting::getValue('app_name', config('app.name')),
                    'order_tags' => [
                        'app_name' => AppSetting::getValue('app_name', config('app.name')),
                    ],
                    'customer_details' => [
                        'customer_id' => 'CUST_' . $order->customer_id,
                        'customer_email' => $order->customer->email ?? '',
                        'customer_phone' => $order->customer_phone,
                    ],
                ]);

                if ($cashfreeOrder->failed()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to create Cashfree order: ' . $cashfreeOrder->body(),
                    ], 502);
                }

                $cashfreeOrderData = $cashfreeOrder->json();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'payment_method' => 'cashfree',
                        'order_id' => $cashfreeOrderData['order_id'] ?? null,
                        'payment_session_id' => $cashfreeOrderData['payment_session_id'] ?? null,
                        'order_token' => $cashfreeOrderData['order_token'] ?? null,
                        'environment' => AppSetting::getValue('cashfree_mode', 'test') === 'test' ? 'sandbox' : 'production',
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree error: ' . $e->getMessage(),
                ], 502);
            }
        }

        return response()->json([
            'success' => false,
            'message' => ucfirst($paymentMethod) . ' payments are not available in this app build.',
        ], 400);
    }

    public function cancelPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $order = Order::where('customer_id', auth()->id())
            ->where('payment_status', 'pending')
            ->findOrFail($request->order_id);

        if ($order->isVisibleToRestaurant() || $order->status !== 'pending') {
            PaymentAttempt::where('order_id', $order->id)
                ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
                ->update(['status' => PaymentAttempt::STATUS_FAILED, 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Payment was not completed. The order remains active.',
            ]);
        }

        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason', 'Payment cancelled before completion'),
            'cancelled_at' => now(),
        ]);

        app(OrderStatusPushService::class)->notifyParticipants(
            $order->fresh(['customer', 'restaurant', 'driver']),
            "Your order #{$order->order_number} has been cancelled.",
            ['customer', 'restaurant', 'driver'],
            'customer'
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled and order closed.',
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_id' => 'required|string',
            'payment_method' => 'required|in:razorpay,stripe,cashfree,card,upi',
            'razorpay_order_id' => 'required_if:payment_method,razorpay|string',
            'razorpay_signature' => 'required_if:payment_method,razorpay|string',
            'stripe_payment_intent_id' => 'required_if:payment_method,stripe|string',
        ]);

        $order = Order::where('customer_id', auth()->id())
            ->findOrFail($request->order_id);

        if ($order->payment_status === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Payment already verified successfully',
            ]);
        }

        $paymentMethod = in_array($request->payment_method, ['card', 'upi'], true)
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : $request->payment_method;

        if ($paymentMethod === 'razorpay') {
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (! $secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.',
                ], 503);
            }

            $payload = $request->razorpay_order_id . '|' . $request->payment_id;
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (! $request->razorpay_signature || ! hash_equals($expectedSignature, $request->razorpay_signature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment signature verification failed.',
                ], 422);
            }
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (! $stripeSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.',
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);

            try {
                $paymentIntent = PaymentIntent::retrieve($request->stripe_payment_intent_id);

                // Verify the payment intent status
                if ($paymentIntent->status !== 'succeeded') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment was not successful. Status: ' . $paymentIntent->status,
                    ], 422);
                }

                // Verify the amount matches
                if ($paymentIntent->amount !== (int) round($order->total * 100)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount does not match order total.',
                    ], 422);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify Stripe payment: ' . $e->getMessage(),
                ], 422);
            }
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (! $clientId || ! $clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.',
                ], 503);
            }

            $expectedPrefixes = [
                'ORDER_' . $order->id . '_',
                'COD_' . $order->id . '_',
            ];
            $matchesExpectedOrder = collect($expectedPrefixes)
                ->contains(fn (string $prefix) => str_starts_with($request->payment_id, $prefix));
            if (! $matchesExpectedOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree order does not match this checkout.',
                ], 422);
            }

            try {
                $response = null;
                $cashfreeOrder = [];
                for ($attempt = 0; $attempt < 4; $attempt++) {
                    $response = Http::withHeaders([
                        'x-api-version' => $apiVersion,
                        'x-client-id' => $clientId,
                        'x-client-secret' => $clientSecret,
                    ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $request->payment_id);

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

                if (! $response || $response->failed()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unable to verify Cashfree payment.',
                    ], 422);
                }

                $cashfreeStatus = strtoupper((string) ($cashfreeOrder['order_status'] ?? 'UNKNOWN'));
                if ($cashfreeStatus !== 'PAID') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cashfree payment is not confirmed yet. Status: ' . $cashfreeStatus . '.',
                    ], 422);
                }

                $paymentsResponse = Http::withHeaders([
                    'x-api-version' => $apiVersion,
                    'x-client-id' => $clientId,
                    'x-client-secret' => $clientSecret,
                ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $request->payment_id . '/payments');
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Cashfree did not return the paid amount for verification.',
                    ], 422);
                }

                $paidMinor = (int) round(((float) $reportedAmount) * 100);
                $expectedMinor = (int) round(((float) $order->total) * 100);
                if ($paidMinor !== $expectedMinor) {
                    \Log::warning('Cashfree paid amount mismatch', [
                        'order_id' => $order->id,
                        'cashfree_order_id' => $request->payment_id,
                        'paid_amount' => $reportedAmount,
                        'expected_amount' => $order->total,
                    ]);
                }

                if ($successfulPayment) {
                    $request->merge(['payment_id' => $successfulPayment['cf_payment_id'] ?? $request->payment_id]);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree verification error: ' . $e->getMessage(),
                ], 422);
            }
        }

        $attempt = PaymentAttempt::where('order_id', $order->id)
            ->where('gateway', $paymentMethod)
            ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
            ->latest()
            ->first();

        if ($attempt) {
            $attempt->forceFill([
                'status' => PaymentAttempt::STATUS_SUCCESS,
                'transaction_id' => $request->payment_id,
                'paid_at' => now(),
            ])->save();

            PaymentAttempt::where('order_id', $order->id)
                ->whereKeyNot($attempt->id)
                ->whereIn('status', [PaymentAttempt::STATUS_PENDING, PaymentAttempt::STATUS_ACTIVE])
                ->update(['status' => PaymentAttempt::STATUS_CANCELLED, 'updated_at' => now()]);
        }

        $order->update([
            'payment_status' => 'success',
            'payment_id' => $request->payment_id,
            'payment_method' => $paymentMethod,
            'payment_gateway' => $paymentMethod,
            'payment_source' => PaymentAttempt::SOURCE_CUSTOMER_APP,
            'paid_at' => now(),
            'online_payment_verified_at' => now(),
        ]);

        Transaction::firstOrCreate(
            [
                'order_id' => $order->id,
                'transaction_id' => $request->payment_id,
            ],
            [
                'user_id' => auth()->id(),
                'amount' => $order->total,
                'type' => 'payment',
                'status' => 'success',
                'payment_method' => $paymentMethod,
            ]
        );

        $order = $order->fresh(['customer', 'restaurant.owner', 'driver']);
        app(OrderReleaseService::class)->releaseToRestaurant($order);
        app(ScratchCardService::class)->issueForRecordedUsage($order);
        app(PromotionRewardSettlementService::class)->settleForOrder($order, 'payment_success');

        $statusPush = app(OrderStatusPushService::class);
        $statusPush->notifyCustomer(
            $order,
            "Payment confirmed. Your order #{$order->order_number} has been placed successfully."
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully',
        ]);
    }

    public function createCheckoutPayment(Request $request)
    {
        $validated = $request->validate([
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
            'customer_name' => 'nullable|string',
            'customer_email' => 'nullable|string',
            'email' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'phone' => 'nullable|string',
            'contact' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'special_instructions' => 'nullable|string|max:1000',
            'scheduled_time' => 'nullable|date|after:now',
            'payment_method' => 'required|in:razorpay,stripe,cashfree,card,upi',
        ]);

        $paymentMethod = in_array($validated['payment_method'], ['card', 'upi'], true)
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : $validated['payment_method'];
        $paymentMethod = strtolower((string) $paymentMethod);

        if (! in_array($paymentMethod, ['razorpay', 'stripe', 'cashfree'], true)) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($paymentMethod) . ' payments are not available in this app build.',
            ], 400);
        }

        if (! filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'success' => false,
                'message' => 'Online payments are currently disabled in admin settings.',
            ], 422);
        }

        try {
            $checkout = app(OrderController::class)->priceCheckoutPayload($request, $paymentMethod);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $amount = round((float) $checkout['pricing']['total'], 2);
        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Order total must be greater than zero for online payment.',
            ], 422);
        }

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
        $reference = 'APPCHK_' . $request->user()->id . '_' . time() . '_' . random_int(1000, 9999);
        $customerName = $request->input('customer_name') ?: ($request->user()->name ?: 'Customer');
        $customerEmail = $this->checkoutCustomerEmail($request);
        $customerPhone = $this->checkoutCustomerPhone($request);

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));

            if (! $key || ! $secret) {
                return response()->json(['success' => false, 'message' => 'Razorpay is not configured.'], 503);
            }

            $razorpayOrder = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', [
                    'receipt' => $reference,
                    'amount' => (int) round($amount * 100),
                    'currency' => $currency,
                    'payment_capture' => 1,
                    'notes' => [
                        'checkout_reference' => $reference,
                        'customer_id' => (string) $request->user()->id,
                    ],
                ]);

            if (! $razorpayOrder->successful()) {
                return response()->json(['success' => false, 'message' => 'Unable to create Razorpay order.'], 502);
            }

            $payload = $razorpayOrder->json();

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway' => 'razorpay',
                    'payment_method' => 'razorpay',
                    'order_id' => $payload['id'] ?? null,
                    'amount' => $payload['amount'] ?? (int) round($amount * 100),
                    'amount_minor' => $payload['amount'] ?? (int) round($amount * 100),
                    'currency' => $payload['currency'] ?? $currency,
                    'key' => $key,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'phone' => $customerPhone,
                    'contact' => $customerPhone,
                    'prefill' => [
                        'name' => $customerName,
                        'email' => $customerEmail,
                        'contact' => $customerPhone,
                    ],
                ],
            ]);
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            $stripeKey = AppSetting::getValue('stripe_key', config('services.stripe.key'));

            if (! $stripeSecret || ! $stripeKey) {
                return response()->json(['success' => false, 'message' => 'Stripe is not configured.'], 503);
            }

            Stripe::setApiKey($stripeSecret);
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($amount * 100),
                'currency' => strtolower($currency),
                'metadata' => [
                    'checkout_reference' => $reference,
                    'customer_id' => (string) $request->user()->id,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway' => 'stripe',
                    'payment_method' => 'stripe',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $stripeKey,
                ],
            ]);
        }

        $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
        $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
        $apiVersion = config('services.cashfree.api_version', '2022-09-01');

        if (! $clientId || ! $clientSecret) {
            return response()->json(['success' => false, 'message' => 'Cashfree is not configured.'], 503);
        }

        $cashfreeOrder = Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ])->post($this->cashfreeBaseUrl() . '/pg/orders', [
            'order_id' => $reference,
            'order_amount' => $amount,
            'order_currency' => $currency,
            'order_note' => 'Checkout payment to ' . AppSetting::getValue('app_name', config('app.name')),
            'order_tags' => [
                'checkout_reference' => $reference,
                'customer_id' => (string) $request->user()->id,
            ],
            'customer_details' => [
                'customer_id' => 'CUST_' . $request->user()->id,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'customer_name' => $customerName,
            ],
        ]);

        if ($cashfreeOrder->failed()) {
            return response()->json(['success' => false, 'message' => 'Unable to create Cashfree order.'], 502);
        }

        $payload = $cashfreeOrder->json();

        return response()->json([
            'success' => true,
            'data' => [
                'gateway' => 'cashfree',
                'payment_method' => 'cashfree',
                'order_id' => $payload['order_id'] ?? $reference,
                'payment_session_id' => $payload['payment_session_id'] ?? null,
                'order_token' => $payload['order_token'] ?? null,
                'environment' => AppSetting::getValue('cashfree_mode', 'test') === 'live' ? 'production' : 'sandbox',
            ],
        ]);
    }

    public function verifyCheckoutPaymentForOrder(Request $request, string $paymentMethod, float $expectedAmount): string
    {
        $paymentMethod = strtolower($paymentMethod);
        $expectedMinor = (int) round($expectedAmount * 100);

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (! $key || ! $secret) {
                throw new \RuntimeException('Razorpay is not configured.');
            }

            $paymentId = (string) $request->input('payment_id', '');
            $razorpayOrderId = (string) $request->input('razorpay_order_id', '');
            $signature = (string) $request->input('razorpay_signature', '');
            if ($paymentId === '' || $razorpayOrderId === '' || $signature === '') {
                throw new \RuntimeException('Razorpay payment proof is missing.');
            }

            $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $paymentId, $secret);
            if (! hash_equals($expectedSignature, $signature)) {
                throw new \RuntimeException('Payment signature verification failed.');
            }

            $response = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->get('https://api.razorpay.com/v1/payments/' . $paymentId);
            if (! $response->successful()) {
                throw new \RuntimeException('Unable to verify Razorpay payment.');
            }

            $payment = $response->json();
            if (($payment['order_id'] ?? null) !== $razorpayOrderId) {
                throw new \RuntimeException('Razorpay order does not match this payment.');
            }
            if (($payment['status'] ?? null) !== 'captured') {
                throw new \RuntimeException('Razorpay payment is not captured yet.');
            }
            if ((int) ($payment['amount'] ?? 0) !== $expectedMinor) {
                throw new \RuntimeException('Payment amount does not match order total.');
            }

            $orderResponse = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->get('https://api.razorpay.com/v1/orders/' . $razorpayOrderId);
            $gatewayOrder = $orderResponse->successful() ? $orderResponse->json() : [];
            $expectedReceiptPrefix = 'APPCHK_' . $request->user()->id . '_';
            if (! str_starts_with((string) ($gatewayOrder['receipt'] ?? ''), $expectedReceiptPrefix)) {
                throw new \RuntimeException('Razorpay order does not match this checkout.');
            }

            $this->ensureCheckoutPaymentUnused($paymentId);

            return $paymentId;
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (! $stripeSecret) {
                throw new \RuntimeException('Stripe is not configured.');
            }

            $intentId = (string) $request->input('stripe_payment_intent_id', $request->input('payment_id', ''));
            if ($intentId === '') {
                throw new \RuntimeException('Stripe payment proof is missing.');
            }

            Stripe::setApiKey($stripeSecret);
            $intent = PaymentIntent::retrieve($intentId);
            if ($intent->status !== 'succeeded') {
                throw new \RuntimeException('Stripe payment was not successful. Status: ' . $intent->status);
            }
            if ((int) $intent->amount !== $expectedMinor) {
                throw new \RuntimeException('Payment amount does not match order total.');
            }
            if ((string) ($intent->metadata->customer_id ?? '') !== (string) $request->user()->id) {
                throw new \RuntimeException('Stripe payment does not match this checkout.');
            }

            $this->ensureCheckoutPaymentUnused($intentId);

            return $intentId;
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');
            if (! $clientId || ! $clientSecret) {
                throw new \RuntimeException('Cashfree is not configured.');
            }

            $cashfreeOrderId = (string) $request->input('payment_id', '');
            $expectedPrefix = 'APPCHK_' . $request->user()->id . '_';
            if ($cashfreeOrderId === '' || ! str_starts_with($cashfreeOrderId, $expectedPrefix)) {
                throw new \RuntimeException('Cashfree order does not match this checkout.');
            }

            $response = null;
            $cashfreeOrder = [];
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $response = Http::withHeaders([
                    'x-api-version' => $apiVersion,
                    'x-client-id' => $clientId,
                    'x-client-secret' => $clientSecret,
                ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $cashfreeOrderId);

                if ($response->successful()) {
                    $cashfreeOrder = $response->json();
                    if (strtoupper((string) ($cashfreeOrder['order_status'] ?? 'UNKNOWN')) === 'PAID') {
                        break;
                    }
                }

                if ($attempt < 3) {
                    usleep(750000);
                }
            }

            if (! $response || $response->failed()) {
                throw new \RuntimeException('Unable to verify Cashfree payment.');
            }
            if (strtoupper((string) ($cashfreeOrder['order_status'] ?? 'UNKNOWN')) !== 'PAID') {
                throw new \RuntimeException('Cashfree payment is not confirmed yet.');
            }

            $paymentsResponse = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $cashfreeOrderId . '/payments');
            $payments = $paymentsResponse->successful() ? $paymentsResponse->json() : [];
            if (isset($payments['payments'])) {
                $payments = $payments['payments'];
            }
            $successfulPayment = collect($payments)->first(
                fn ($payment) => strtoupper((string) ($payment['payment_status'] ?? '')) === 'SUCCESS'
            );
            $reportedAmount = $successfulPayment['payment_amount'] ?? $cashfreeOrder['order_amount'] ?? null;
            if ($reportedAmount === null || (int) round(((float) $reportedAmount) * 100) !== $expectedMinor) {
                throw new \RuntimeException('Payment amount does not match order total.');
            }

            $transactionId = (string) ($successfulPayment['cf_payment_id'] ?? $cashfreeOrderId);
            $this->ensureCheckoutPaymentUnused($transactionId);

            return $transactionId;
        }

        throw new \RuntimeException('Unsupported payment gateway.');
    }

    private function ensureCheckoutPaymentUnused(string $transactionId): void
    {
        if ($transactionId === '') {
            throw new \RuntimeException('Payment proof is missing.');
        }

        if (Order::where('payment_id', $transactionId)->exists()
            || Transaction::where('transaction_id', $transactionId)->exists()) {
            throw new \RuntimeException('This payment has already been used for an order.');
        }
    }

    private function checkoutCustomerEmail(Request $request): string
    {
        $email = trim((string) ($request->input('customer_email') ?: $request->input('email') ?: $request->user()->email ?: ''));

        return $email !== '' ? $email : 'customer' . $request->user()->id . '@foodflow.local';
    }

    private function checkoutCustomerPhone(Request $request): string
    {
        $phone = trim((string) ($request->input('customer_phone') ?: $request->input('phone') ?: $request->input('contact') ?: $request->user()->phone ?: ''));
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) >= 10) {
            return substr($digits, -10);
        }

        return $phone !== '' ? $phone : '9999999999';
    }
    private function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'test') === 'test'
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }
}