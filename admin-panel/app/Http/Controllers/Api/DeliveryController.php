<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\AppSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderStatusPushService;
use App\Services\PayoutCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeliveryController extends Controller
{
    public function verifyOtp(Request $request, PayoutCalculationService $payoutCalculation, $orderId)
    {
        $request->validate([
            'otp' => 'required|string|size:4',
            'payment_mode' => 'required|in:cash,online',
            'cash_collected' => 'required_if:payment_mode,cash|boolean',
        ]);
        
        $order = Order::where('driver_id', auth()->id())
            ->where('status', 'on_the_way')
            ->findOrFail($orderId);

        if (!$order->delivery_otp || !hash_equals((string) $order->delivery_otp, (string) $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        $paymentResult = $this->resolvePayment($order, $request);
        if (!$paymentResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $paymentResult['message'],
            ], $paymentResult['status'] ?? 422);
        }

        DB::transaction(function () use ($order, $request, $payoutCalculation) {
            $order->status = 'delivered';
            $order->delivered_at = now();
            $order->otp_verified = true;
            $order->otp_verified_at = now();
            $order->delivery_payment_mode = $request->payment_mode;

            if ($request->payment_mode === 'cash') {
                $order->payment_method = 'cod';
                $order->payment_status = 'success';
                $order->cash_collected_amount = $order->total;
                $order->cash_collected_at = now();
                $this->debitDriverWalletForCodCollection($order);
            } else {
                $order->payment_status = 'success';
                $order->online_payment_verified_at = $order->online_payment_verified_at ?: now();
            }

            $order->save();

            $payoutCalculation->processOrderEarnings($order->fresh());
        });

        app(OrderStatusPushService::class)->notifyParticipants(
            $order->fresh(['customer', 'restaurant'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Delivery completed and payment confirmed.',
            'data' => $order->fresh()
        ]);
    }
    
    public function resendOtp($orderId)
    {
        $order = Order::where('driver_id', auth()->id())
            ->where('status', 'on_the_way')
            ->findOrFail($orderId);
            
        $otp = $order->generateDeliveryOtp();
        
        // Send OTP via SMS
        // $this->sendSms($order->customer_phone, "Your delivery OTP is: $otp");
        
        return response()->json([
            'success' => true,
            'message' => 'OTP resent successfully'
        ]);
    }

    private function resolvePayment(Order $order, Request $request): array
    {
        if ($request->payment_mode === 'cash') {
            if (!$request->boolean('cash_collected')) {
                return [
                    'success' => false,
                    'message' => 'Confirm cash collection before completing delivery.',
                    'status' => 422,
                ];
            }

            return ['success' => true];
        }

        if ($order->payment_status === 'success') {
            return ['success' => true];
        }

        if (!$order->payment_id) {
            return [
                'success' => false,
                'message' => 'Online payment is not completed yet. Ask customer to complete payment before delivery.',
                'status' => 409,
            ];
        }

        if (!$this->verifyRazorpayPayment($order)) {
            return [
                'success' => false,
                'message' => 'Razorpay payment is not captured. Please wait for payment completion or collect cash.',
                'status' => 409,
            ];
        }

        return ['success' => true];
    }

    private function verifyRazorpayPayment(Order $order): bool
    {
        $key = config('services.razorpay.key');
        $secret = config('services.razorpay.secret');

        if (!$key || !$secret) {
            return false;
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(10)
            ->get("https://api.razorpay.com/v1/payments/{$order->payment_id}");

        if (!$response->ok()) {
            return false;
        }

        $payment = $response->json();
        $expectedAmount = (int) round(((float) $order->total) * 100);

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        return ($payment['status'] ?? null) === 'captured'
            && (int) ($payment['amount'] ?? 0) >= $expectedAmount
            && strtoupper((string) ($payment['currency'] ?? $currency)) === $currency;
    }

    private function debitDriverWalletForCodCollection(Order $order): void
    {
        if (!$order->driver_id || (float) $order->total <= 0) {
            return;
        }

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        $wallet = Wallet::where('user_id', $order->driver_id)->lockForUpdate()->first()
            ?: Wallet::create([
                'user_id' => $order->driver_id,
                'balance' => 0,
                'locked_balance' => 0,
                'currency' => $currency,
                'is_active' => true,
            ]);

        $exists = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('reference_type', 'driver_cod_collection')
            ->where('reference_id', $order->id)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = (float) $order->total;
        $wallet->decrement('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => 'driver_cod_collection',
            'reference_id' => $order->id,
            'description' => 'COD cash collected for order #' . ($order->order_number ?? $order->id),
            'meta' => ['source' => 'cod_collection'],
        ]);
    }
}
