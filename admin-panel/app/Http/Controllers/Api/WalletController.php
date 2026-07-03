<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\GiftCard;
use App\Models\GiftCardRedemption;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $wallet = $this->walletFor($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
                'minimum_driver_balance' => (float) AppSetting::getValue('driver_minimum_wallet_balance', 0),
                'transactions' => $wallet->transactions()
                    ->latest()
                    ->limit(30)
                    ->get(),
            ],
        ]);
    }

    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
            'description' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $isDriver = $user->hasRole('delivery_partner') || $user->hasRole('driver');
        $restaurant = null;

        if (! $isDriver) {
            $restaurantQuery = Restaurant::query()->whereHas('owner', function ($query) use ($user) {
                $query->whereKey($user->id);
            });

            if (! empty($validated['restaurant_id'])) {
                $restaurantQuery->whereKey($validated['restaurant_id']);
            }

            $restaurant = $restaurantQuery->first();
        }

        if (! $isDriver && ! $restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Restaurant owner or delivery partner access is required to request settlement.',
            ], 403);
        }

        $amount = (float) $validated['amount'];
        $currencyCode = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
        $gateway = AppSetting::getValue('payout_gateway_provider', AppSetting::getValue('payment_gateway_provider', 'razorpay'));

        try {
            $result = DB::transaction(function () use ($user, $restaurant, $isDriver, $amount, $currencyCode, $gateway, $validated) {
                $wallet = $this->walletFor($user, true);

                if ((float) $wallet->balance < $amount) {
                    abort(422, 'Insufficient wallet balance for this withdrawal request.');
                }

                $wallet->decrement('balance', $amount);
                $wallet->increment('locked_balance', $amount);
                $wallet->refresh();

                $payout = Payout::create([
                    'restaurant_id' => $restaurant?->id,
                    'driver_id' => $isDriver ? $user->id : null,
                    'amount' => $amount,
                    'gross_amount' => $amount,
                    'net_amount' => $amount,
                    'currency' => $currencyCode,
                    'status' => 'pending',
                    'gateway' => $gateway,
                    'vendor_type' => $isDriver ? 'driver' : 'restaurant',
                    'vendor_id' => $isDriver ? $user->id : $restaurant->id,
                    'period_start' => now(),
                    'period_end' => now(),
                    'created_by' => $user->id,
                    'idempotency_key' => 'manual_' . ($isDriver ? 'driver_' : 'restaurant_') . (string) \Illuminate\Support\Str::uuid(),
                ]);

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'balance_after' => $wallet->balance,
                    'reference_type' => 'payout',
                    'reference_id' => $payout->id,
                    'description' => $validated['description'] ?? 'Manual settlement withdrawal requested',
                ]);

                return compact('wallet', 'payout', 'transaction');
            });

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted for settlement.',
                'data' => $result,
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'payment_method' => 'nullable|in:razorpay,stripe,cashfree,card,upi',
            'description' => 'nullable|string|max:255',
        ]);

        $requestedMethod = $validated['payment_method']
            ?? AppSetting::getValue('payment_gateway_provider', 'razorpay');
        $paymentMethod = in_array($requestedMethod, ['card', 'upi'], true)
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : $requestedMethod;
        $currencyCode = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        $recharge = WalletRecharge::create([
            'user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'currency' => $currencyCode,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'meta' => [
                'description' => $validated['description'] ?? 'Wallet top-up',
            ],
        ]);

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));

            if (!$key || !$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.',
                ], 503);
            }

            $gatewayOrder = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', [
                    'receipt' => 'wallet_' . $recharge->id,
                    'amount' => (int) round($recharge->amount * 100),
                    'currency' => $recharge->currency,
                    'payment_capture' => 1,
                ]);

            if (!$gatewayOrder->successful()) {
                $recharge->update(['status' => 'failed']);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create Razorpay wallet recharge.',
                ], 502);
            }

            $gatewayData = $gatewayOrder->json();
            $recharge->update(['gateway_order_id' => $gatewayData['id'] ?? null]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wallet_recharge_id' => $recharge->id,
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

            if (!$stripeSecret || !$stripeKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.',
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($recharge->amount * 100),
                'currency' => strtolower($currencyCode),
                'metadata' => [
                    'wallet_recharge_id' => $recharge->id,
                    'user_id' => $request->user()->id,
                ],
            ]);

            $recharge->update(['gateway_payment_id' => $paymentIntent->id]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wallet_recharge_id' => $recharge->id,
                    'payment_method' => 'stripe',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $stripeKey,
                ],
            ]);
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');
            $mode = AppSetting::getValue('cashfree_mode', 'live');

            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.',
                ], 503);
            }

            $cashfreeOrderId = 'WALLET_' . $recharge->id . '_' . time();
            $cashfreeOrder = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->post($this->cashfreeBaseUrl() . '/pg/orders', [
                'order_id' => $cashfreeOrderId,
                'order_amount' => round($recharge->amount, 2),
                'order_currency' => $recharge->currency,
                'order_note' => 'Wallet recharge for ' . AppSetting::getValue('app_name', config('app.name')),
                'order_tags' => [
                    'app_name' => AppSetting::getValue('app_name', config('app.name')),
                ],
                'customer_details' => [
                    'customer_id' => 'USER_' . $request->user()->id,
                    'customer_email' => $request->user()->email ?? '',
                    'customer_phone' => $request->user()->phone ?? '',
                ],
            ]);

            if ($cashfreeOrder->failed()) {
                $recharge->update(['status' => 'failed']);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create Cashfree wallet recharge: ' . $cashfreeOrder->body(),
                ], 502);
            }

            $gatewayData = $cashfreeOrder->json();
            $recharge->update(['gateway_order_id' => $gatewayData['order_id'] ?? $cashfreeOrderId]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wallet_recharge_id' => $recharge->id,
                    'payment_method' => 'cashfree',
                    'order_id' => $gatewayData['order_id'] ?? $cashfreeOrderId,
                    'payment_session_id' => $gatewayData['payment_session_id'] ?? null,
                    'order_token' => $gatewayData['order_token'] ?? null,
                    'environment' => $mode === 'test' ? 'sandbox' : 'production',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => ucfirst($paymentMethod) . ' wallet recharge is not available.',
        ], 400);
    }

    public function verifyTopUp(Request $request)
    {
        $validated = $request->validate([
            'wallet_recharge_id' => 'required|exists:wallet_recharges,id',
            'payment_id' => 'required|string',
            'payment_method' => 'required|in:razorpay,stripe,cashfree,card,upi',
            'razorpay_order_id' => 'required_if:payment_method,razorpay|string',
            'razorpay_signature' => 'required_if:payment_method,razorpay|string',
            'stripe_payment_intent_id' => 'required_if:payment_method,stripe|string',
        ]);

        $recharge = WalletRecharge::where('user_id', $request->user()->id)
            ->findOrFail($validated['wallet_recharge_id']);

        if ($recharge->status === 'success') {
            return response()->json([
                'success' => true,
                'message' => 'Wallet recharge already verified.',
            ]);
        }

        $paymentMethod = in_array($validated['payment_method'], ['card', 'upi'], true)
            ? $recharge->payment_method
            : $validated['payment_method'];

        if ($paymentMethod !== $recharge->payment_method) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method does not match recharge.',
            ], 422);
        }

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (!$key || !$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.',
                ], 503);
            }

            $payload = $validated['razorpay_order_id'] . '|' . $validated['payment_id'];
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expectedSignature, $validated['razorpay_signature'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment signature verification failed.',
                ], 422);
            }

            $paymentResponse = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->get('https://api.razorpay.com/v1/payments/' . $validated['payment_id']);

            if (!$paymentResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to confirm Razorpay payment.',
                ], 422);
            }

            $payment = $paymentResponse->json();
            $expectedAmount = (int) round($recharge->amount * 100);
            if (($payment['order_id'] ?? null) !== $recharge->gateway_order_id
                || ($payment['order_id'] ?? null) !== $validated['razorpay_order_id']
                || (int) ($payment['amount'] ?? 0) !== $expectedAmount
                || strtolower($payment['currency'] ?? '') !== strtolower($recharge->currency)
                || ($payment['status'] ?? null) !== 'captured') {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay payment was not confirmed for this recharge.',
                ], 422);
            }
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (!$stripeSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.',
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);
            $paymentIntent = PaymentIntent::retrieve($validated['stripe_payment_intent_id']);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not successful. Status: ' . $paymentIntent->status,
                ], 422);
            }

            if ($paymentIntent->amount !== (int) round($recharge->amount * 100)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount does not match recharge amount.',
                ], 422);
            }
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.',
                ], 503);
            }

            $cashfreeOrderId = $recharge->gateway_order_id ?: $validated['payment_id'];
            $response = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $cashfreeOrderId . '/payments');

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify Cashfree payment.',
                ], 422);
            }

            $payments = $response->json();
            if (isset($payments['payments'])) {
                $payments = $payments['payments'];
            }

            $successfulPayment = collect($payments)->first(function ($payment) use ($recharge) {
                return ($payment['payment_status'] ?? null) === 'SUCCESS'
                    && (float) ($payment['order_amount'] ?? $recharge->amount) === (float) $recharge->amount;
            });

            if (!$successfulPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree payment was not successful.',
                ], 422);
            }

            $validated['payment_id'] = $successfulPayment['cf_payment_id'] ?? $cashfreeOrderId;
        }

        $wallet = DB::transaction(function () use ($request, $recharge, $validated, $paymentMethod) {
            $lockedRecharge = WalletRecharge::whereKey($recharge->id)->lockForUpdate()->firstOrFail();
            if ($lockedRecharge->status === 'success') {
                return $this->walletFor($request->user(), true);
            }

            $wallet = $this->walletFor($request->user(), true);
            $wallet->increment('balance', $lockedRecharge->amount);
            $wallet->refresh();

            $lockedRecharge->update([
                'status' => 'success',
                'gateway_payment_id' => $validated['payment_id'],
                'gateway_signature' => $validated['razorpay_signature'] ?? null,
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'credit',
                'amount' => $lockedRecharge->amount,
                'balance_after' => $wallet->balance,
                'reference_type' => 'self_topup',
                'reference_id' => $lockedRecharge->id,
                'description' => data_get($lockedRecharge->meta, 'description', 'Wallet top-up'),
                'meta' => [
                    'payment_method' => $paymentMethod,
                    'payment_id' => $validated['payment_id'],
                ],
            ]);

            return $wallet;
        });

        return response()->json([
            'success' => true,
            'message' => 'Wallet recharged successfully.',
            'data' => $wallet,
        ]);
    }

    public function redeemGiftCard(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $code = strtoupper(trim($validated['code']));

        try {
            $result = DB::transaction(function () use ($request, $code) {
                $giftCard = GiftCard::where('code', $code)->lockForUpdate()->first();

                if (! $giftCard) {
                    abort(404, 'Gift card not found.');
                }

                if (! $giftCard->is_redeemable) {
                    abort(422, 'This gift card is no longer available.');
                }

                $alreadyRedeemed = GiftCardRedemption::where('gift_card_id', $giftCard->id)
                    ->where('user_id', $request->user()->id)
                    ->exists();

                if ($alreadyRedeemed) {
                    abort(422, 'You have already redeemed this gift card.');
                }

                $wallet = $this->walletFor($request->user(), true);
                $wallet->increment('balance', $giftCard->amount);
                $wallet->refresh();

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'type' => 'credit',
                    'amount' => $giftCard->amount,
                    'balance_after' => $wallet->balance,
                    'reference_type' => 'gift_card',
                    'reference_id' => $giftCard->id,
                    'description' => 'Gift card redeemed: ' . $giftCard->code,
                    'meta' => [
                        'gift_card_code' => $giftCard->code,
                        'gift_card_title' => $giftCard->title,
                    ],
                ]);

                GiftCardRedemption::create([
                    'gift_card_id' => $giftCard->id,
                    'user_id' => $wallet->user_id,
                    'wallet_transaction_id' => $transaction->id,
                    'amount' => $giftCard->amount,
                    'redeemed_at' => now(),
                ]);

                $giftCard->increment('redeemed_count');

                return [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                    'amount' => $giftCard->amount,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Gift card redeemed successfully.',
                'data' => $result,
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }

    private function walletFor($user, bool $lock = false): Wallet
    {
        $query = Wallet::where('user_id', $user->id);
        if ($lock) {
            $query->lockForUpdate();
        }

        $currencyCode = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        return $query->first() ?: Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'locked_balance' => 0,
            'currency' => $currencyCode,
            'is_active' => true,
        ]);
    }

    private function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'live') === 'test'
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }
}
