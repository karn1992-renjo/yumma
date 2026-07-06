<?php
// app/Services/RefundService.php

namespace App\Services;

use App\Models\Order;
use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\RefundPolicy;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Stripe\Refund;
use Stripe\Stripe;

class RefundService
{
    protected $payoutCalculation;
    
    public function __construct(PayoutCalculationService $payoutCalculation)
    {
        $this->payoutCalculation = $payoutCalculation;
    }
    
    /**
     * Process refund for an order
     */
    public function processRefund(Order $order, string $reason, ?string $customAmount = null)
    {
        DB::beginTransaction();
        
        try {
            $order = Order::with(['restaurant.owner'])
                ->lockForUpdate()
                ->findOrFail($order->id);
            if (in_array($order->refund_status, ['processing', 'completed'], true)) {
                throw new \RuntimeException('This refund is already being processed or completed.');
            }

            $wasDelivered = $order->status === 'delivered';
            $policy = RefundPolicy::getActivePolicy();
            $refundAmount = $customAmount ?? $policy->calculateRefundAmount($order, $reason);
            
            if ($refundAmount <= 0) {
                throw new \Exception('Refund amount must be greater than 0');
            }
            
            // Update order with refund details
            $order->update([
                'refund_amount' => $refundAmount,
                'refund_reason' => $reason,
                'refund_status' => 'processing',
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason
            ]);
            
            // Process actual refund based on payment method
            try {
                $refundSuccess = $this->processPaymentRefund($order, $refundAmount);
            } catch (\Throwable $gatewayError) {
                Log::warning('Gateway refund failed, falling back to wallet refund', [
                    'order_id' => $order->id,
                    'payment_method' => $order->payment_method,
                    'message' => $gatewayError->getMessage(),
                ]);
                $refundSuccess = $this->refundToWalletFallback($order, (float) $refundAmount, $reason);
            }

            if (!$refundSuccess) {
                $refundSuccess = $this->refundToWalletFallback($order, (float) $refundAmount, $reason);
            }
            
            if ($refundSuccess) {
                $order->update([
                    'refund_status' => 'completed',
                    'refund_processed_at' => now(),
                    'payment_status' => 'refunded'
                ]);
                
                // Reverse earnings if order was delivered
                if ($wasDelivered) {
                    $this->reverseEarnings($order->fresh(), (float) $refundAmount);
                }

                app(BranchManagementService::class)->reverseRefund($order->fresh(), (float) $refundAmount);
                
                DB::commit();
                
                // Send refund notification
                $this->sendRefundNotification($order);
                
                return [
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'refund_amount' => $refundAmount
                ];
            }
            
            DB::rollback();
            return [
                'success' => false,
                'message' => 'Failed to process payment refund'
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Refund processing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Refund processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process payment gateway refund
     */
    protected function processPaymentRefund(Order $order, $amount)
    {
        $paymentMethod = $this->resolveRefundPaymentMethod($order);

        switch ($paymentMethod) {
            case 'razorpay':
                return $this->razorpayRefund($order, $amount);
            case 'stripe':
                return $this->stripeRefund($order, $amount);
            case 'cashfree':
                return $this->cashfreeRefund($order, $amount);
            case 'paystack':
                return $this->paystackRefund($order, $amount);
            case 'mollie':
                return $this->mollieRefund($order, $amount);
            case 'mercadopago':
                return $this->mercadoPagoRefund($order, $amount);
            case 'cod':
                // For COD, no actual refund needed
                return true;
            case 'wallet':
                $this->creditCustomerWallet($order, (float) $amount, 'Wallet refund for cancelled order');
                return true;
            default:
                Log::warning('Unsupported refund payment method', [
                    'order_id' => $order->id,
                    'payment_method' => $paymentMethod,
                    'stored_payment_method' => $order->payment_method,
                ]);
                return false;
        }
    }

    protected function resolveRefundPaymentMethod(Order $order): string
    {
        $transactionMethod = Transaction::where('order_id', $order->id)
            ->where('type', 'payment')
            ->where('status', 'success')
            ->latest('id')
            ->value('payment_method');

        return strtolower((string) ($transactionMethod ?: $order->payment_method));
    }

    protected function resolveGatewayPaymentReference(Order $order): ?string
    {
        $transactionReference = Transaction::where('order_id', $order->id)
            ->where('type', 'payment')
            ->where('status', 'success')
            ->latest('id')
            ->value('transaction_id');

        return $transactionReference ?: $order->payment_id;
    }
    
    protected function razorpayRefund($order, $amount)
    {
        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        $paymentReference = $this->resolveGatewayPaymentReference($order);

        if (!$key || !$secret || !$paymentReference) {
            throw new \Exception('Razorpay refund is not configured for this order.');
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->asJson()
            ->post("https://api.razorpay.com/v1/payments/{$paymentReference}/refund", [
                'amount' => (int) round(((float) $amount) * 100),
                'speed' => 'normal',
                'notes' => [
                    'order_id' => (string) $order->id,
                    'order_number' => (string) $order->order_number,
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('Razorpay refund failed: ' . $response->body());
        }

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => $response->json('id'),
            'payment_method' => 'razorpay',
        ]);

        return true;
    }
    
    protected function stripeRefund($order, $amount)
    {
        $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));

        $paymentReference = $this->resolveGatewayPaymentReference($order);

        if (!$stripeSecret || !$paymentReference) {
            throw new \Exception('Stripe refund is not configured for this order.');
        }

        Stripe::setApiKey($stripeSecret);

        $refund = Refund::create([
            'payment_intent' => $paymentReference,
            'amount' => (int) round(((float) $amount) * 100),
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
            ],
        ]);

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => $refund->status === 'failed' ? 'failed' : 'success',
            'transaction_id' => $refund->id,
            'payment_method' => 'stripe',
        ]);

        if ($refund->status === 'failed') {
            throw new \Exception('Stripe refund failed.');
        }

        return true;
    }

    protected function cashfreeRefund($order, $amount)
    {
        $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
        $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
        $apiVersion = AppSetting::getValue('cashfree_api_version', config('services.cashfree.api_version', '2022-09-01'));
        $cashfreeOrderId = $this->resolveGatewayPaymentReference($order);

        if (!$clientId || !$clientSecret || !$cashfreeOrderId) {
            throw new \Exception('Cashfree refund is not configured for this order.');
        }

        $refundId = 'refund_' . $order->id . '_' . now()->timestamp;
        $response = Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
            'Content-Type' => 'application/json',
        ])->post($this->cashfreeBaseUrl() . '/pg/orders/' . $cashfreeOrderId . '/refunds', [
            'refund_amount' => round((float) $amount, 2),
            'refund_id' => $refundId,
            'refund_note' => 'Refund for order #' . $order->order_number,
        ]);

        if ($response->failed()) {
            throw new \Exception('Cashfree refund failed: ' . $response->body());
        }

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => $response->json('cf_refund_id') ?? $refundId,
            'payment_method' => 'cashfree',
        ]);

        return true;
    }

    protected function paystackRefund($order, $amount)
    {
        $secretKey = AppSetting::getValue('paystack_secret_key');
        $paymentReference = $this->resolveGatewayPaymentReference($order);

        if (!$secretKey || !$paymentReference) {
            throw new \Exception('Paystack refund is not configured for this order.');
        }

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->post('https://api.paystack.co/refund', [
                'transaction' => $paymentReference,
                'amount' => (int) round(((float) $amount) * 100),
                'currency' => strtoupper(AppSetting::getValue('currency_code', 'NGN') ?: 'NGN'),
            ]);

        if ($response->failed()) {
            throw new \Exception('Paystack refund failed: ' . $response->body());
        }

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => (string) ($response->json('data.reference')
                ?? $response->json('data.id')
                ?? ('paystack_refund_' . $order->id . '_' . now()->timestamp)),
            'payment_method' => 'paystack',
        ]);

        return true;
    }

    protected function mollieRefund($order, $amount)
    {
        $apiKey = AppSetting::getValue('mollie_key');
        $paymentReference = $this->resolveGatewayPaymentReference($order);

        if (!$apiKey || !$paymentReference) {
            throw new \Exception('Mollie refund is not configured for this order.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post('https://api.mollie.com/v2/payments/' . $paymentReference . '/refunds', [
                'amount' => [
                    'currency' => strtoupper(AppSetting::getValue('currency_code', 'EUR') ?: 'EUR'),
                    'value' => number_format((float) $amount, AppSetting::currencyDecimals(), '.', ''),
                ],
                'description' => 'Refund for order #' . $order->order_number,
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => (string) $order->order_number,
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('Mollie refund failed: ' . $response->body());
        }

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => $response->json('id') ?? ('mollie_refund_' . $order->id . '_' . now()->timestamp),
            'payment_method' => 'mollie',
        ]);

        return true;
    }

    protected function mercadoPagoRefund($order, $amount)
    {
        $accessToken = AppSetting::getValue('mercadopago_access_token');
        $paymentReference = $this->resolveGatewayPaymentReference($order);

        if (!$accessToken || !$paymentReference) {
            throw new \Exception('Mercado Pago refund is not configured for this order.');
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post('https://api.mercadopago.com/v1/payments/' . $paymentReference . '/refunds', [
                'amount' => round((float) $amount, AppSetting::currencyDecimals()),
            ]);

        if ($response->failed()) {
            throw new \Exception('Mercado Pago refund failed: ' . $response->body());
        }

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => (string) ($response->json('id')
                ?? data_get($response->json(), '0.id')
                ?? ('mercadopago_refund_' . $order->id . '_' . now()->timestamp)),
            'payment_method' => 'mercadopago',
        ]);

        return true;
    }

    protected function creditCustomerWallet(Order $order, float $amount, string $reason): void
    {
        if (!$order->customer_id || $amount <= 0) {
            return;
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $order->customer_id],
            ['balance' => 0, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]
        );

        $wallet->increment('balance', $amount);
        $wallet->refresh();

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $order->customer_id,
            'type' => 'refund_credit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => 'order_refund',
            'reference_id' => $order->id,
            'description' => "Refund for order #{$order->order_number}: {$reason}",
        ]);
    }

    protected function refundToWalletFallback(Order $order, float $amount, string $reason): bool
    {
        if (!$order->customer_id || $amount <= 0) {
            return false;
        }

        $this->creditCustomerWallet(
            $order,
            $amount,
            $reason . ' (credited to wallet because direct gateway refund is unavailable)'
        );

        Transaction::create([
            'order_id' => $order->id,
            'user_id' => $order->customer_id,
            'amount' => $amount,
            'type' => 'refund',
            'status' => 'success',
            'transaction_id' => 'wallet_refund_' . $order->id . '_' . now()->timestamp,
            'payment_method' => 'wallet',
        ]);

        return true;
    }
    
    /**
     * Reverse earnings for cancelled delivered orders
     */
    protected function reverseEarnings(Order $order, float $refundAmount): void
    {
        $ratio = min(1, max(0, $refundAmount / max(0.01, (float) $order->total)));
        $restaurantOwner = $order->restaurant?->owner;

        if ($restaurantOwner) {
            $this->reversePayeeEarning(
                $restaurantOwner,
                $order,
                round((float) $order->restaurant_earning * $ratio, 2),
                $order->restaurant_payout_id ? Payout::find($order->restaurant_payout_id) : null,
                'restaurant_earning_refund'
            );
        }

        if ($order->driver_id && ($driver = User::find($order->driver_id))) {
            $this->reversePayeeEarning(
                $driver,
                $order,
                round((float) $order->driver_earning * $ratio, 2),
                $order->driver_payout_id ? Payout::find($order->driver_payout_id) : null,
                'driver_earning_refund'
            );
        }
    }

    protected function reversePayeeEarning(
        User $payee,
        Order $order,
        float $amount,
        ?Payout $payout,
        string $referenceType
    ): void {
        if ($amount <= 0 || WalletTransaction::where('reference_type', $referenceType)
            ->where('reference_id', $order->id)
            ->exists()) {
            return;
        }

        $wallet = Wallet::where('user_id', $payee->id)->lockForUpdate()->first();
        if (! $wallet) {
            return;
        }

        if ($payout?->status === 'pending') {
            $amount = min($amount, (float) $payout->amount, (float) $wallet->locked_balance);
            if ($amount <= 0) {
                return;
            }

            $payoutAdjustments = $this->payoutRefundAdjustments($payout, $order, $amount);
            $wallet->decrement('locked_balance', $amount);
            $payout->update($payoutAdjustments + [
                'amount' => max(0, (float) $payout->amount - $amount),
                'net_amount' => max(0, (float) $payout->net_amount - $amount),
            ]);
        } else {
            // Funds already sent become a recoverable negative wallet balance.
            $wallet->decrement('balance', $amount);
        }

        $wallet->refresh();
        $payee->update([
            'total_earned' => max(0, (float) ($payee->total_earned ?? 0) - $amount),
            'pending_payout' => max(0, (float) ($payee->pending_payout ?? 0) - $amount),
        ]);

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => 'debit',
            'amount' => $amount,
            'balance_after' => $wallet->balance,
            'reference_type' => $referenceType,
            'reference_id' => $order->id,
            'description' => 'Earning reversed for refunded order #' . ($order->order_number ?? $order->id),
            'meta' => ['payout_id' => $payout?->id, 'source' => 'refund'],
        ]);
    }

    protected function payoutRefundAdjustments(Payout $payout, Order $order, float $reversedAmount): array
    {
        $ratio = min(1, max(0, $reversedAmount / max(0.01, $payout->restaurant_id
            ? (float) $order->restaurant_earning
            : (float) $order->driver_earning)));
        $subtract = fn ($current, $orderAmount) => max(0, round((float) $current - ((float) $orderAmount * $ratio), 2));

        if ($payout->restaurant_id) {
            return [
                'gross_amount' => $subtract($payout->gross_amount, $order->subtotal),
                'platform_commission' => $subtract($payout->platform_commission, $order->platform_commission),
                'gst_on_commission' => $subtract($payout->gst_on_commission, $order->gst_on_commission),
                'payment_gateway_fee' => $subtract($payout->payment_gateway_fee, $order->payment_gateway_fee),
                'delivery_fee' => $subtract($payout->delivery_fee, $order->delivery_fee),
            ];
        }

        return [
            'gross_amount' => $subtract($payout->gross_amount, $order->driver_delivery_base),
            'delivery_fee' => $subtract($payout->delivery_fee, $order->driver_delivery_base),
            'platform_commission' => $subtract(
                $payout->platform_commission,
                (float) $order->admin_delivery_commission + (float) $order->driver_deduction
            ),
            'admin_delivery_commission' => 0,
            'driver_deduction' => $subtract(
                $payout->driver_deduction,
                (float) $order->admin_delivery_commission + (float) $order->driver_deduction
            ),
            'batch_bonus' => $subtract($payout->batch_bonus, $order->batch_bonus),
        ];
    }
    
    /**
     * Send refund notification to customer
     */
    protected function sendRefundNotification(Order $order)
    {
        // Send SMS
        // Send Email
        // Send Push Notification
    }

    protected function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'test') === 'live'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com';
    }
}
