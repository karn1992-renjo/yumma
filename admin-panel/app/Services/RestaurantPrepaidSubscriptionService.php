<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionGatewayResolver;
use App\Support\JsonHttp;
use App\Support\RazorpayCapture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pay-as-you-go restaurant plans (the stories-only plan, usable by commission-billed restaurants). No gateway
 * mandate: each period is one ordinary payment (Razorpay/Cashfree Orders API) that the app confirms right after
 * its SDK reports success, the same way Gold membership and the ad wallet work. When the period ends nothing is
 * charged again; the restaurant pays for the next one (or upgrades).
 */
class RestaurantPrepaidSubscriptionService
{
    public function __construct(private readonly SubscriptionGatewayResolver $gateways)
    {
    }

    /** Plans that are bought one payment at a time. */
    public static function isPrepaidPlan(SubscriptionPlan $plan): bool
    {
        return $plan->scope === 'stories_addon';
    }

    /**
     * @return array{subscription: RestaurantSubscription, checkout: array<string, mixed>}
     */
    public function start(Restaurant $restaurant, SubscriptionPlan $plan, string $gatewayName, ?RestaurantSubscription $replacing = null): array
    {
        if (! in_array($gatewayName, SubscriptionGatewayResolver::SUPPORTED, true)) {
            throw new RuntimeException("Unsupported payment method: {$gatewayName}");
        }
        if (! in_array($gatewayName, $this->gateways->availableGateways(), true)) {
            throw new RuntimeException("{$gatewayName} is not available right now. Please choose a different payment method.");
        }

        // A checkout that was started but never paid must not block a new one.
        $restaurant->subscriptions()->where('scope', $plan->scope)->where('status', 'pending_authorization')->where('payment_mode', 'prepaid')->update([
            'status' => 'cancelled', 'cancelled_at' => now(),
        ]);

        $live = $restaurant->subscriptions()->where('scope', $plan->scope)->whereIn('status', ['trialing', 'active', 'past_due'])->latest()->first();
        if ($live && $live->isLive() && ! $replacing) {
            throw new RuntimeException('This restaurant already has an active stories plan. Use upgrade to change it.');
        }

        $subscription = RestaurantSubscription::create([
            'restaurant_id' => $restaurant->id,
            'subscription_plan_id' => $plan->id,
            'scope' => $plan->scope,
            'status' => 'pending_authorization',
            'gateway' => $gatewayName,
            'auto_renew' => false,
            'payment_mode' => 'prepaid',
            'replaces_subscription_id' => $replacing?->id,
        ]);

        try {
            $checkout = $this->createOrder($subscription, $plan, $restaurant, $gatewayName);
        } catch (\Throwable $e) {
            $subscription->delete();       // no order, nothing to pay: leave nothing behind
            throw $e;
        }
        $subscription->update(['gateway_order_id' => $checkout['gateway_order_id']]);

        return ['subscription' => $subscription->fresh(), 'checkout' => $checkout['client']];
    }

    /** Called by the app right after its SDK reports success; trusts only the gateway's own answer. */
    public function verifyAndActivate(RestaurantSubscription $subscription, array $payment): RestaurantSubscription
    {
        if ($subscription->status !== 'pending_authorization') {
            return $subscription;        // duplicate call
        }
        if (($payment['payment_method'] ?? null) !== $subscription->gateway) {
            throw new RuntimeException('Payment method does not match this checkout.');
        }
        $plan = $subscription->plan;
        if (! $plan) {
            throw new RuntimeException('This plan no longer exists.');
        }
        $amount = (float) $plan->price;

        $subscription->gateway === 'razorpay'
            ? $this->verifyRazorpay($subscription, $payment, $amount)
            : $this->verifyCashfree($subscription, $payment, $amount);

        $this->activate($subscription, ['amount' => $amount, 'currency' => $plan->currency ?: 'INR', 'payment_id' => $payment['payment_id'] ?? null]);

        return $subscription->fresh();
    }

    private function activate(RestaurantSubscription $subscription, array $charge): void
    {
        DB::transaction(function () use ($subscription, $charge) {
            $locked = RestaurantSubscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_authorization') {
                return;
            }
            $start = now();
            $end = $locked->plan?->nextPeriodEndFrom($start) ?? $start->copy()->addMonth();

            $locked->update(['status' => 'active', 'current_period_start' => $start, 'current_period_end' => $end, 'orders_used_in_period' => 0]);
            SubscriptionPayment::create([
                'restaurant_subscription_id' => $locked->id, 'amount' => $charge['amount'] ?? 0, 'currency' => $charge['currency'] ?? 'INR', 'status' => 'success',
                'gateway' => $locked->gateway, 'gateway_charge_id' => $charge['payment_id'] ?? null, 'period_start' => $start, 'period_end' => $end, 'meta' => $charge,
            ]);

            // An upgrade replaces the plan it was bought from; the old one ends only now that the new one is paid.
            if ($locked->replaces_subscription_id) {
                RestaurantSubscription::whereKey($locked->replaces_subscription_id)->whereIn('status', ['trialing', 'active', 'past_due'])
                    ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
            }
        });
    }

    /** @return array{gateway_order_id: string, client: array<string, mixed>} */
    private function createOrder(RestaurantSubscription $subscription, SubscriptionPlan $plan, Restaurant $restaurant, string $gatewayName): array
    {
        $amount = (float) $plan->price;
        $currency = strtoupper($plan->currency ?: 'INR');

        if ($gatewayName === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (! $key || ! $secret) {
                throw new RuntimeException('Razorpay is not configured.');
            }
            $response = Http::withBasicAuth($key, $secret)->acceptJson()->asJson()->post('https://api.razorpay.com/v1/orders', [
                'receipt' => 'rsub_' . $subscription->id, 'amount' => (int) round($amount * 100), 'currency' => $currency, 'payment_capture' => 1,
            ]);
            if (! $response->successful() || ! ($response->json('id'))) {
                throw new RuntimeException('Unable to create Razorpay order: ' . $response->body());
            }
            $data = $response->json();

            return ['gateway_order_id' => $data['id'], 'client' => [
                'payment_method' => 'razorpay', 'order_id' => $data['id'], 'amount' => $data['amount'] ?? (int) round($amount * 100),
                'currency' => $data['currency'] ?? $currency, 'key' => $key,
            ]];
        }

        $clientId = AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'));
        $clientSecret = AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
        $mode = AppSetting::getValue('cashfree_mode', 'live');
        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('Cashfree is not configured.');
        }
        $owner = $restaurant->owner;
        $orderId = 'RSUB_' . $subscription->id . '_' . now()->timestamp;
        $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
        $response = JsonHttp::post(Http::withHeaders([
            'x-api-version' => config('services.cashfree.api_version', '2022-09-01'), 'x-client-id' => $clientId, 'x-client-secret' => $clientSecret,
        ]), $baseUrl . '/pg/orders', [
            'order_id' => $orderId, 'order_amount' => (float) round($amount, 2), 'order_currency' => $currency, 'order_note' => $plan->name,
            'customer_details' => ['customer_id' => 'REST_' . $restaurant->id, 'customer_email' => $owner?->email ?: ($restaurant->email ?: 'owner@example.com'),
                'customer_phone' => $owner?->phone ?: ($restaurant->phone ?: '')],
        ]);
        if ($response->failed()) {
            throw new RuntimeException('Unable to create Cashfree order: ' . $response->body());
        }
        $data = $response->json();

        return ['gateway_order_id' => $data['order_id'] ?? $orderId, 'client' => [
            'payment_method' => 'cashfree', 'order_id' => $data['order_id'] ?? $orderId, 'payment_session_id' => $data['payment_session_id'] ?? null,
            'environment' => $mode === 'test' ? 'sandbox' : 'production',
        ]];
    }

    private function verifyRazorpay(RestaurantSubscription $subscription, array $payment, float $amount): void
    {
        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $key || ! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }
        $orderId = $payment['razorpay_order_id'] ?? null;
        $paymentId = $payment['payment_id'] ?? null;
        $signature = $payment['razorpay_signature'] ?? null;
        if (! $orderId || ! $paymentId || ! $signature) {
            throw new RuntimeException('Missing Razorpay payment details.');
        }
        if (! hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $secret), $signature)) {
            throw new RuntimeException('Payment signature verification failed.');
        }
        $response = Http::withBasicAuth($key, $secret)->acceptJson()->get('https://api.razorpay.com/v1/payments/' . $paymentId);
        if (! $response->successful()) {
            throw new RuntimeException('Unable to confirm Razorpay payment.');
        }
        $data = RazorpayCapture::ensureCaptured($response->json(), $key, $secret, (int) round($amount * 100));   // authorized -> captured
        if (($data['order_id'] ?? null) !== $subscription->gateway_order_id || (int) ($data['amount'] ?? 0) !== (int) round($amount * 100) || ($data['status'] ?? null) !== 'captured') {
            throw new RuntimeException('Razorpay payment was not confirmed for this plan.');
        }
    }

    private function verifyCashfree(RestaurantSubscription $subscription, array $payment, float $amount): void
    {
        $clientId = AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'));
        $clientSecret = AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
        $mode = AppSetting::getValue('cashfree_mode', 'live');
        if (! $clientId || ! $clientSecret) {
            throw new RuntimeException('Cashfree is not configured.');
        }
        $baseUrl = $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
        $response = Http::withHeaders([
            'x-api-version' => config('services.cashfree.api_version', '2022-09-01'), 'x-client-id' => $clientId, 'x-client-secret' => $clientSecret,
        ])->get($baseUrl . '/pg/orders/' . ($subscription->gateway_order_id ?: ($payment['payment_id'] ?? '')) . '/payments');
        if ($response->failed()) {
            throw new RuntimeException('Unable to verify Cashfree payment.');
        }
        $rows = $response->json();
        $rows = $rows['payments'] ?? $rows;
        $ok = collect($rows)->first(fn ($p) => ($p['payment_status'] ?? null) === 'SUCCESS' && (float) ($p['order_amount'] ?? $amount) === (float) $amount);
        if (! $ok) {
            throw new RuntimeException('Cashfree payment was not successful.');
        }
    }
}
