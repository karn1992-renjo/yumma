<?php

namespace App\Http\Controllers\Api;

use App\Events\NewOrderEvent;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\OrderReleaseService;
use App\Services\OrderStatusPushService;
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

        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'cancellation_reason' => $request->input('reason', 'Payment cancelled before completion'),
            'cancelled_at' => now(),
        ]);

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

            $expectedPrefix = 'ORDER_' . $order->id . '_';
            if (! str_starts_with($request->payment_id, $expectedPrefix)) {
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

        $order->update([
            'payment_status' => 'success',
            'payment_id' => $request->payment_id,
            'payment_method' => $paymentMethod,
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

    private function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'test') === 'test'
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }
}
