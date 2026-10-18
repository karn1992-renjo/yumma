<?php

namespace App\Services\Subscription;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Subscriptions API (https://razorpay.com/docs/api/payments/subscriptions/)
 * -- distinct product from the one-off Orders API used by checkout/ad-wallet
 * top-up. Razorpay Plans only support period in {daily,weekly,monthly,yearly}
 * with an integer `interval` multiplier -- there is no native "quarterly", so
 * it's modelled as period=monthly, interval=3.
 *
 * Uses the SAME account credentials (razorpay_key/razorpay_secret) as the
 * rest of this app's Razorpay integration -- it's the same merchant account,
 * just a different API family.
 */
class RazorpaySubscriptionGateway implements SubscriptionGatewayInterface
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function isConfigured(): bool
    {
        return (bool) AppSetting::getValue('razorpay_key', config('services.razorpay.key'))
            && (bool) AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
    }

    private function key(): string
    {
        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        if (! $key) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return $key;
    }

    private function secret(): string
    {
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return $secret;
    }

    private function http()
    {
        return Http::withBasicAuth($this->key(), $this->secret())->acceptJson()->asJson();
    }

    public function resolveCustomerId(string $name, string $email, ?string $phone, ?string $existingGatewayCustomerId): string
    {
        if ($existingGatewayCustomerId) {
            return $existingGatewayCustomerId;
        }

        $response = $this->http()->post(self::BASE_URL . '/customers', [
            'name' => $name,
            'email' => $email,
            'contact' => $phone,
            'fail_existing' => '0',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Razorpay customer: ' . $response->body());
        }

        $customerId = $response->json('id');
        if (! $customerId) {
            throw new RuntimeException('Razorpay customer response missing id.');
        }

        return $customerId;
    }

    public function resolvePlanId(SubscriptionPlan $plan): string
    {
        if ($plan->razorpay_plan_id) {
            return $plan->razorpay_plan_id;
        }

        [$period, $interval] = match ($plan->billing_period) {
            'monthly' => ['monthly', 1],
            'quarterly' => ['monthly', 3],
            'yearly' => ['yearly', 1],
            default => throw new RuntimeException("Unsupported billing_period: {$plan->billing_period}"),
        };

        $response = $this->http()->post(self::BASE_URL . '/plans', [
            'period' => $period,
            'interval' => $interval,
            'item' => [
                'name' => $plan->name,
                'amount' => (int) round((float) $plan->price * 100),
                'currency' => $plan->currency ?: 'INR',
                'description' => $plan->description,
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Razorpay plan: ' . $response->body());
        }

        $gatewayPlanId = $response->json('id');
        if (! $gatewayPlanId) {
            throw new RuntimeException('Razorpay plan response missing id.');
        }

        $plan->update(['razorpay_plan_id' => $gatewayPlanId]);

        return $gatewayPlanId;
    }

    public function createSubscription(RestaurantSubscription|CustomerSubscription $subscription, string $gatewayCustomerId, string $gatewayPlanId): array
    {
        $payload = [
            'plan_id' => $gatewayPlanId,
            'customer_id' => $gatewayCustomerId,
            'customer_notify' => 1,
            // Razorpay requires a bounded total_count; 120 cycles (10 years
            // of monthly billing) is effectively "ongoing" for this product
            // without relying on an infinite/unsupported value.
            'total_count' => 120,
        ];

        if ($subscription->trial_ends_at) {
            $payload['start_at'] = $subscription->trial_ends_at->timestamp;
        }

        $response = $this->http()->post(self::BASE_URL . '/subscriptions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Razorpay subscription: ' . $response->body());
        }

        $data = $response->json();
        $gatewaySubscriptionId = $data['id'] ?? null;
        if (! $gatewaySubscriptionId) {
            throw new RuntimeException('Razorpay subscription response missing id.');
        }

        return [
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'checkout' => [
                'subscription_id' => $gatewaySubscriptionId,
                'key' => $this->key(),
                'short_url' => $data['short_url'] ?? null,
            ],
        ];
    }

    public function cancel(RestaurantSubscription|CustomerSubscription $subscription): bool
    {
        if (! $subscription->gateway_subscription_id) {
            return true;
        }

        $response = $this->http()->post(
            self::BASE_URL . '/subscriptions/' . $subscription->gateway_subscription_id . '/cancel',
            ['cancel_at_cycle_end' => 0]
        );

        return $response->successful();
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader, ?string $timestampHeader = null): bool
    {
        $webhookSecret = AppSetting::getValue('razorpay_subscription_webhook_secret');
        if (! $webhookSecret) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, (string) $signatureHeader);
    }
}
