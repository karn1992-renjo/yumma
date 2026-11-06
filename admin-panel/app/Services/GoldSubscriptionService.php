<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\SubscriptionGatewayResolver;
use App\Support\JsonHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Customer "Gold" membership -- pay-as-you-go, NOT a recurring gateway
 * mandate. Each period is a single one-time payment (the same Orders API
 * this app already uses for checkout/ad-wallet top-up -- see
 * AdWalletController::topUp()/verifyTopUp(), the pattern mirrored below),
 * verified SYNCHRONOUSLY right after the client's gateway SDK reports
 * success. No webhook anywhere in this path: a membership can only ever be
 * stuck in pending_authorization because the customer simply never
 * finished paying, never because of a missed/misconfigured webhook. When a
 * paid period ends, nothing auto-charges the customer again -- they come
 * back and pay for the next period themselves. (CustomerSubscription::
 * isLive()/scopeLive() treat an elapsed current_period_end as not-live even
 * though `status` stays 'active' -- nothing else ever flips it, by design.)
 */
class GoldSubscriptionService
{
    public function __construct(
        private readonly SubscriptionGatewayResolver $gateways,
        private readonly PushNotificationService $pushNotifications,
    ) {
    }

    /**
     * Starts a membership period. A plan with a trial activates immediately
     * for free (no payment at all) -- otherwise creates a pending row plus
     * a one-time gateway order for the client to pay via its SDK, confirmed
     * by verifyAndActivate() below once payment completes.
     */
    public function startCheckout(User $user, SubscriptionPlan $plan, string $gatewayName): array
    {
        if (! $plan->hasTrial()) {
            if (! in_array($gatewayName, SubscriptionGatewayResolver::SUPPORTED, true)) {
                throw new RuntimeException("Unsupported payment method: {$gatewayName}");
            }
            if (! in_array($gatewayName, $this->gateways->availableGateways(), true)) {
                throw new RuntimeException("{$gatewayName} is not available right now. Please choose a different payment method.");
            }
        }

        // A pending_authorization row older than this never had its payment
        // completed (the customer backed out of checkout, or the app was
        // killed mid-flow) -- nothing else ever expires it, so without this
        // it permanently blocks every future subscribe attempt with a
        // flatly wrong "already have a membership" error.
        CustomerSubscription::where('user_id', $user->id)
            ->where('status', 'pending_authorization')
            ->where('created_at', '<', now()->subMinutes(30))
            ->delete();

        $hasExisting = CustomerSubscription::where('user_id', $user->id)->live()->exists()
            || CustomerSubscription::where('user_id', $user->id)->where('status', 'pending_authorization')->exists();

        if ($hasExisting) {
            throw new RuntimeException('You already have a Gold membership.');
        }

        if ($plan->hasTrial()) {
            $trialEndsAt = now()->addDays($plan->trial_days);
            $subscription = CustomerSubscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
                'auto_renew' => false,
            ]);

            $this->notifyActivated($subscription);

            return [
                'requires_payment' => false,
                'subscription' => $subscription,
                'checkout' => null,
            ];
        }

        $subscription = CustomerSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending_authorization',
            'gateway' => $gatewayName,
            'auto_renew' => false,
        ]);

        try {
            $checkout = $this->createOneTimeOrder($subscription, $plan, $gatewayName);
        } catch (\Throwable $e) {
            // The gateway call failed outright (bad credentials, transient
            // API error, etc.) -- delete the row immediately rather than
            // leaving it stuck in pending_authorization with no order and no
            // way to retry.
            $subscription->delete();
            throw $e;
        }

        $subscription->update(['gateway_order_id' => $checkout['gateway_order_id']]);

        return [
            'requires_payment' => true,
            'subscription' => $subscription->fresh(),
            'checkout' => $checkout['client'],
        ];
    }

    /**
     * One-time gateway order for this period's price -- the Orders API, not
     * the Subscriptions/mandate API. Mirrors AdWalletController::topUp().
     */
    private function createOneTimeOrder(CustomerSubscription $subscription, SubscriptionPlan $plan, string $gatewayName): array
    {
        $amount = (float) $plan->price;
        $currency = strtoupper($plan->currency ?: 'INR');

        if ($gatewayName === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (! $key || ! $secret) {
                throw new RuntimeException('Razorpay is not configured.');
            }

            $response = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', [
                    'receipt' => 'gold_' . $subscription->id,
                    'amount' => (int) round($amount * 100),
                    'currency' => $currency,
                    'payment_capture' => 1,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Unable to create Razorpay order: ' . $response->body());
            }

            $data = $response->json();
            if (! ($data['id'] ?? null)) {
                throw new RuntimeException('Razorpay order response missing id.');
            }

            return [
                'gateway_order_id' => $data['id'],
                'client' => [
                    'payment_method' => 'razorpay',
                    'order_id' => $data['id'],
                    'amount' => $data['amount'] ?? (int) round($amount * 100),
                    'currency' => $data['currency'] ?? $currency,
                    'key' => $key,
                ],
            ];
        }

        $clientId = AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'));
        $clientSecret = AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
        $apiVersion = config('services.cashfree.api_version', '2022-09-01');
        $mode = AppSetting::getValue('cashfree_mode', 'live');
        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        $user = $subscription->user;
        $orderId = 'GOLD_' . $subscription->id . '_' . now()->timestamp;
        $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';

        // (float) + JsonHttp::post() -- Cashfree rejects a bare-integer
        // order_amount for a whole-rupee total. See App\Support\JsonHttp.
        $response = JsonHttp::post(Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ]), $baseUrl . '/pg/orders', [
            'order_id' => $orderId,
            'order_amount' => (float) round($amount, 2),
            'order_currency' => $currency,
            'order_note' => 'Gold membership',
            'customer_details' => [
                'customer_id' => 'USER_' . $subscription->user_id,
                'customer_email' => $user?->email ?: 'customer@example.com',
                'customer_phone' => $user?->phone ?: '',
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Unable to create Cashfree order: ' . $response->body());
        }

        $data = $response->json();

        return [
            'gateway_order_id' => $data['order_id'] ?? $orderId,
            'client' => [
                'payment_method' => 'cashfree',
                'order_id' => $data['order_id'] ?? $orderId,
                'payment_session_id' => $data['payment_session_id'] ?? null,
                'environment' => $mode === 'test' ? 'sandbox' : 'production',
            ],
        ];
    }

    /**
     * Synchronous, server-side payment verification -- called by the client
     * immediately after its gateway SDK reports success. No webhook
     * anywhere in this path (mirrors AdWalletController::verifyTopUp()).
     */
    public function verifyAndActivate(CustomerSubscription $subscription, array $payment): void
    {
        if ($subscription->status !== 'pending_authorization') {
            // Already verified (duplicate client call) or stale -- an
            // idempotent no-op rather than an error keeps retried calls safe.
            return;
        }

        $gatewayName = $subscription->gateway;
        if ($gatewayName !== ($payment['payment_method'] ?? null)) {
            throw new RuntimeException('Payment method does not match this membership checkout.');
        }

        $plan = $subscription->plan ?: $subscription->loadMissing('plan')->plan;
        if (! $plan) {
            throw new RuntimeException('Membership plan no longer exists.');
        }
        $amount = (float) $plan->price;

        if ($gatewayName === 'razorpay') {
            $this->verifyRazorpayPayment($subscription, $payment, $amount);
        } else {
            $this->verifyCashfreePayment($subscription, $payment, $amount);
        }

        $this->activate($subscription, [
            'amount' => $amount,
            'currency' => $plan->currency ?: 'INR',
            'payment_id' => $payment['payment_id'] ?? null,
        ]);
    }

    private function verifyRazorpayPayment(CustomerSubscription $subscription, array $payment, float $amount): void
    {
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $orderId = $payment['razorpay_order_id'] ?? null;
        $paymentId = $payment['payment_id'] ?? null;
        $signature = $payment['razorpay_signature'] ?? null;

        if (! $orderId || ! $paymentId || ! $signature) {
            throw new RuntimeException('Missing Razorpay payment details.');
        }

        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Payment signature verification failed.');
        }

        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->get('https://api.razorpay.com/v1/payments/' . $paymentId);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm Razorpay payment.');
        }

        $data = $response->json();
        $expectedAmount = (int) round($amount * 100);
        if (($data['order_id'] ?? null) !== $subscription->gateway_order_id
            || (int) ($data['amount'] ?? 0) !== $expectedAmount
            || ($data['status'] ?? null) !== 'captured') {
            throw new RuntimeException('Razorpay payment was not confirmed for this membership.');
        }
    }

    private function verifyCashfreePayment(CustomerSubscription $subscription, array $payment, float $amount): void
    {
        $clientId = AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'));
        $clientSecret = AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
        $apiVersion = config('services.cashfree.api_version', '2022-09-01');
        $mode = AppSetting::getValue('cashfree_mode', 'live');
        $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';

        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        $orderId = $subscription->gateway_order_id ?: ($payment['payment_id'] ?? null);
        $response = Http::withHeaders([
            'x-api-version' => $apiVersion,
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ])->get($baseUrl . '/pg/orders/' . $orderId . '/payments');

        if ($response->failed()) {
            throw new RuntimeException('Unable to verify Cashfree payment.');
        }

        $payments = $response->json();
        $payments = $payments['payments'] ?? $payments;

        $successful = collect($payments)->first(fn ($p) => ($p['payment_status'] ?? null) === 'SUCCESS'
            && (float) ($p['order_amount'] ?? $amount) === (float) $amount);

        if (! $successful) {
            throw new RuntimeException('Cashfree payment was not successful.');
        }
    }

    private function activate(CustomerSubscription $subscription, array $chargeData): void
    {
        DB::transaction(function () use ($subscription, $chargeData) {
            $locked = CustomerSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_authorization') {
                return;
            }

            $periodStart = now();
            $periodEnd = $locked->plan?->nextPeriodEndFrom($periodStart) ?? $periodStart->copy()->addMonth();

            $locked->update([
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'orders_used_in_period' => 0,
            ]);

            SubscriptionPayment::create([
                'restaurant_subscription_id' => null,
                'customer_subscription_id' => $locked->id,
                'amount' => $chargeData['amount'] ?? 0,
                'currency' => $chargeData['currency'] ?? 'INR',
                'status' => 'success',
                'gateway' => $locked->gateway,
                'gateway_charge_id' => $chargeData['payment_id'] ?? null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'meta' => $chargeData,
            ]);
        });

        $this->notifyActivated($subscription->fresh());
    }

    /**
     * Admin-granted membership -- same end state as a real paid activation
     * (status=active immediately, same welcome push) but with no payment
     * involved. Used by Admin\GoldMemberController's "grant by mobile
     * number" action.
     */
    public function grantManually(User $user, SubscriptionPlan $plan, string $grantedByLabel): CustomerSubscription
    {
        $periodStart = now();

        $subscription = CustomerSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $plan->nextPeriodEndFrom($periodStart),
            'auto_renew' => false,
            'gateway' => null,
            'gateway_payment_method_label' => $grantedByLabel,
        ]);

        $this->notifyActivated($subscription);

        return $subscription;
    }

    /**
     * Best-effort "Welcome to Gold" push on a genuinely new/reactivated
     * membership -- silent on failure (missing FCM token, Firebase not
     * configured, etc.), same as every other personalized push in this
     * codebase (see PushNotificationService::sendToUser()'s doc comment).
     */
    private function notifyActivated(CustomerSubscription $subscription): void
    {
        $subscription->loadMissing(['user', 'plan']);
        $user = $subscription->user;
        if (! $user) {
            return;
        }

        $appName = AppSetting::getValue('app_name', config('app.name', 'FoodFlow'));
        $planName = $subscription->plan?->name ?: 'Gold';

        try {
            $this->pushNotifications->sendToUser(
                $user,
                "Welcome to {$appName} {$planName}! 🎉",
                'Your membership is now active -- enjoy free delivery on every order.',
                [
                    'type' => 'gold_membership_activated',
                    'subscription_id' => (string) $subscription->id,
                ],
                'customer'
            );
        } catch (\Throwable $e) {
            Log::warning('Gold membership activation push failed.', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->sendWelcomeEmail($subscription, $user);

        try {
            app(\App\Services\WhatsApp\WhatsAppNotifier::class)->goldWelcome($subscription);
        } catch (\Throwable $e) {
            Log::warning('Gold WhatsApp welcome failed.', ['subscription_id' => $subscription->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Branded "Welcome to Gold" email. Sent after the HTTP response so a slow
     * mail server never delays the customer's payment confirmation, and never
     * allowed to fail the activation.
     */
    private function sendWelcomeEmail(CustomerSubscription $subscription, User $user): void
    {
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $send = function () use ($subscription, $user) {
            try {
                \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\GoldMembershipWelcomeMail($subscription));
            } catch (\Throwable $e) {
                Log::warning('Gold membership welcome email failed.', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        app()->runningInConsole() ? $send() : app()->terminating($send);
    }

    /**
     * Pay-as-you-go has nothing to cancel on the gateway side (no recurring
     * mandate exists) -- this just stops counting the membership as live
     * right away instead of waiting out the already-paid period.
     */
    public function cancel(CustomerSubscription $subscription): void
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
    }

    /**
     * Cheap exists() check used at checkout/delivery-fee-calculation time --
     * NOT eager-loaded, call sparingly (once per pricing calculation). Only
     * reflects membership status, not order-limit capacity -- see
     * activeSubscriptionFor() when the caller also needs to check/consume
     * the order cap.
     */
    public function isActiveFor(int $userId): bool
    {
        return CustomerSubscription::where('user_id', $userId)->live()->exists();
    }

    /**
     * The live membership row itself (with its plan loaded), for callers
     * that need to check hasOrderCapacity() before waiving a fee and later
     * incrementOrderUsage() once the order is actually placed.
     */
    public function activeSubscriptionFor(int $userId): ?CustomerSubscription
    {
        return CustomerSubscription::where('user_id', $userId)->live()->with('plan')->first();
    }

    public function incrementOrderUsage(CustomerSubscription $subscription): void
    {
        $subscription->increment('orders_used_in_period');
    }
}
