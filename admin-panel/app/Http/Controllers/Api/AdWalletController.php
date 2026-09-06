<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AppSetting;
use App\Models\RestaurantAdWallet;
use App\Models\RestaurantAdWalletRecharge;
use App\Services\AdWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class AdWalletController extends Controller
{
    use ResolvesRestaurantContext;

    public function show(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $wallet = RestaurantAdWallet::firstOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                'balance' => 0,
                'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
                'transactions' => $wallet->transactions()->latest()->paginate(20),
            ],
        ]);
    }

    public function topUp(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'payment_method' => 'required|in:razorpay,stripe,cashfree',
        ]);

        $paymentMethod = $validated['payment_method'];
        $currencyCode = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        $recharge = RestaurantAdWalletRecharge::create([
            'restaurant_id' => $restaurant->id,
            'amount' => $validated['amount'],
            'currency' => $currencyCode,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'meta' => ['description' => 'Ad wallet top-up'],
        ]);

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));

            if (! $key || ! $secret) {
                return response()->json(['success' => false, 'message' => 'Razorpay is not configured.'], 503);
            }

            $gatewayOrder = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', [
                    'receipt' => 'ad_wallet_' . $recharge->id,
                    'amount' => (int) round($recharge->amount * 100),
                    'currency' => $recharge->currency,
                    'payment_capture' => 1,
                ]);

            if (! $gatewayOrder->successful()) {
                $recharge->update(['status' => 'failed']);

                return response()->json(['success' => false, 'message' => 'Unable to create Razorpay order.'], 502);
            }

            $gatewayData = $gatewayOrder->json();
            $recharge->update(['gateway_order_id' => $gatewayData['id'] ?? null]);

            return response()->json([
                'success' => true,
                'data' => [
                    'recharge_id' => $recharge->id,
                    'payment_method' => 'razorpay',
                    'order_id' => $gatewayData['id'] ?? null,
                    'amount' => $gatewayData['amount'] ?? (int) round($recharge->amount * 100),
                    'currency' => $gatewayData['currency'] ?? $recharge->currency,
                    'key' => $key,
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
                'amount' => (int) round($recharge->amount * 100),
                'currency' => strtolower($currencyCode),
                'metadata' => [
                    'ad_wallet_recharge_id' => $recharge->id,
                    'restaurant_id' => $restaurant->id,
                ],
            ]);

            $recharge->update(['gateway_payment_id' => $paymentIntent->id]);

            return response()->json([
                'success' => true,
                'data' => [
                    'recharge_id' => $recharge->id,
                    'payment_method' => 'stripe',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $stripeKey,
                ],
            ]);
        }

        $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
        $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
        $apiVersion = config('services.cashfree.api_version', '2022-09-01');
        $mode = AppSetting::getValue('cashfree_mode', 'live');

        if (! $clientId || ! $clientSecret) {
            return response()->json(['success' => false, 'message' => 'Cashfree is not configured.'], 503);
        }

        $cashfreeOrderId = 'AD_WALLET_' . $recharge->id . '_' . time();
        $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
        $cashfreeOrder = Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ])->post($baseUrl . '/pg/orders', [
            'order_id' => $cashfreeOrderId,
            'order_amount' => round($recharge->amount, 2),
            'order_currency' => $recharge->currency,
            'order_note' => 'Ad wallet top-up',
            'customer_details' => [
                'customer_id' => 'RESTAURANT_' . $restaurant->id,
                'customer_email' => $restaurant->email ?? '',
                'customer_phone' => $restaurant->phone ?? '',
            ],
        ]);

        if ($cashfreeOrder->failed()) {
            $recharge->update(['status' => 'failed']);

            return response()->json(['success' => false, 'message' => 'Unable to create Cashfree order: ' . $cashfreeOrder->body()], 502);
        }

        $gatewayData = $cashfreeOrder->json();
        $recharge->update(['gateway_order_id' => $gatewayData['order_id'] ?? $cashfreeOrderId]);

        return response()->json([
            'success' => true,
            'data' => [
                'recharge_id' => $recharge->id,
                'payment_method' => 'cashfree',
                'order_id' => $gatewayData['order_id'] ?? $cashfreeOrderId,
                'payment_session_id' => $gatewayData['payment_session_id'] ?? null,
                'environment' => $mode === 'test' ? 'sandbox' : 'production',
            ],
        ]);
    }

    public function verifyTopUp(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        if (! $restaurant) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
        }

        $validated = $request->validate([
            'recharge_id' => 'required|exists:restaurant_ad_wallet_recharges,id',
            'payment_id' => 'required|string',
            'payment_method' => 'required|in:razorpay,stripe,cashfree',
            'razorpay_order_id' => 'required_if:payment_method,razorpay|string',
            'razorpay_signature' => 'required_if:payment_method,razorpay|string',
            'stripe_payment_intent_id' => 'required_if:payment_method,stripe|string',
        ]);

        $recharge = RestaurantAdWalletRecharge::where('restaurant_id', $restaurant->id)
            ->findOrFail($validated['recharge_id']);

        if ($recharge->status === 'success') {
            return response()->json(['success' => true, 'message' => 'Recharge already verified.']);
        }

        $paymentMethod = $validated['payment_method'];
        if ($paymentMethod !== $recharge->payment_method) {
            return response()->json(['success' => false, 'message' => 'Payment method does not match recharge.'], 422);
        }

        if ($paymentMethod === 'razorpay') {
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (! $secret) {
                return response()->json(['success' => false, 'message' => 'Razorpay is not configured.'], 503);
            }

            $payload = $validated['razorpay_order_id'] . '|' . $validated['payment_id'];
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (! hash_equals($expectedSignature, $validated['razorpay_signature'])) {
                return response()->json(['success' => false, 'message' => 'Payment signature verification failed.'], 422);
            }

            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $paymentResponse = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->get('https://api.razorpay.com/v1/payments/' . $validated['payment_id']);

            if (! $paymentResponse->successful()) {
                return response()->json(['success' => false, 'message' => 'Unable to confirm Razorpay payment.'], 422);
            }

            $payment = $paymentResponse->json();
            $expectedAmount = (int) round($recharge->amount * 100);
            if (($payment['order_id'] ?? null) !== $recharge->gateway_order_id
                || (int) ($payment['amount'] ?? 0) !== $expectedAmount
                || ($payment['status'] ?? null) !== 'captured') {
                return response()->json(['success' => false, 'message' => 'Razorpay payment was not confirmed for this recharge.'], 422);
            }
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (! $stripeSecret) {
                return response()->json(['success' => false, 'message' => 'Stripe is not configured.'], 503);
            }

            Stripe::setApiKey($stripeSecret);
            $paymentIntent = PaymentIntent::retrieve($validated['stripe_payment_intent_id']);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json(['success' => false, 'message' => 'Payment was not successful. Status: ' . $paymentIntent->status], 422);
            }
            if ($paymentIntent->amount !== (int) round($recharge->amount * 100)) {
                return response()->json(['success' => false, 'message' => 'Payment amount does not match recharge amount.'], 422);
            }
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');
            $mode = AppSetting::getValue('cashfree_mode', 'live');
            $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';

            if (! $clientId || ! $clientSecret) {
                return response()->json(['success' => false, 'message' => 'Cashfree is not configured.'], 503);
            }

            $cashfreeOrderId = $recharge->gateway_order_id ?: $validated['payment_id'];
            $response = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->get($baseUrl . '/pg/orders/' . $cashfreeOrderId . '/payments');

            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Unable to verify Cashfree payment.'], 422);
            }

            $payments = $response->json();
            $payments = $payments['payments'] ?? $payments;

            $successfulPayment = collect($payments)->first(fn ($payment) =>
                ($payment['payment_status'] ?? null) === 'SUCCESS'
                && (float) ($payment['order_amount'] ?? $recharge->amount) === (float) $recharge->amount);

            if (! $successfulPayment) {
                return response()->json(['success' => false, 'message' => 'Cashfree payment was not successful.'], 422);
            }

            $validated['payment_id'] = $successfulPayment['cf_payment_id'] ?? $cashfreeOrderId;
        }

        $transaction = DB::transaction(function () use ($restaurant, $recharge, $validated, $paymentMethod) {
            $lockedRecharge = RestaurantAdWalletRecharge::whereKey($recharge->id)->lockForUpdate()->firstOrFail();
            if ($lockedRecharge->status === 'success') {
                return null;
            }

            $lockedRecharge->update([
                'status' => 'success',
                'gateway_payment_id' => $validated['payment_id'],
                'gateway_signature' => $validated['razorpay_signature'] ?? null,
            ]);

            return app(AdWalletService::class)->credit(
                $restaurant,
                (float) $lockedRecharge->amount,
                'ad_wallet_recharge',
                $lockedRecharge->id,
                'Ad wallet top-up'
            );
        });

        $wallet = RestaurantAdWallet::where('restaurant_id', $restaurant->id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Ad wallet recharged successfully.',
            'data' => ['wallet' => $wallet],
        ]);
    }
}
