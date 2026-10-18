<?php

namespace App\Services\Subscription;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cashfree Subscriptions / recurring-payments API (docs.cashfree.com/docs/subscriptions)
 * -- a separate product family from the one-off Orders API used at checkout
 * and for ad-wallet top-up (AdWalletController), but the same merchant
 * account/credentials (cashfree_key/cashfree_secret) and the same
 * sandbox/production host split already used there.
 *
 * Cashfree plan intervals are {DAY,WEEK,MONTH,YEAR} with an integer
 * `plan_intervals` multiplier -- same "no native quarterly" situation as
 * Razorpay, modelled as MONTH x3.
 */
class CashfreeSubscriptionGateway implements SubscriptionGatewayInterface
{
    public function isConfigured(): bool
    {
        return (bool) AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'))
            && (bool) AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
    }

    private function clientId(): string
    {
        $id = AppSetting::getValue('cashfree_key', config('services.cashfree.client_id'));
        if (! $id) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        return $id;
    }

    private function clientSecret(): string
    {
        $secret = AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret'));
        if (! $secret) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        return $secret;
    }

    private function baseUrl(): string
    {
        $mode = AppSetting::getValue('cashfree_mode', 'live');

        return $mode === 'test' ? 'https://sandbox.cashfree.com' : 'https://api.cashfree.com';
    }

    private function apiVersion(): string
    {
        return config('services.cashfree.api_version', '2022-09-01');
    }

    private function http()
    {
        return Http::withHeaders([
            'x-api-version' => $this->apiVersion(),
            'x-client-id' => $this->clientId(),
            'x-client-secret' => $this->clientSecret(),
        ])->acceptJson()->asJson();
    }

    public function resolveCustomerId(string $name, string $email, ?string $phone, ?string $existingGatewayCustomerId): string
    {
        // Cashfree's Subscriptions API takes customer_details inline on
        // every subscription create call rather than a separate reusable
        // customer object -- there is nothing to provision ahead of time.
        // Returning the email as a stable per-customer reference so the
        // shared createSubscription() flow still has a non-empty value to
        // store on the local row.
        return $existingGatewayCustomerId ?: $email;
    }

    public function resolvePlanId(SubscriptionPlan $plan): string
    {
        if ($plan->cashfree_plan_id) {
            return $plan->cashfree_plan_id;
        }

        [$intervalType, $intervals] = match ($plan->billing_period) {
            'monthly' => ['MONTH', 1],
            'quarterly' => ['MONTH', 3],
            'yearly' => ['YEAR', 1],
            default => throw new RuntimeException("Unsupported billing_period: {$plan->billing_period}"),
        };

        // Cashfree plan ids are caller-chosen (not gateway-generated), must
        // be unique per merchant -- prefix + our own plan id keeps it stable
        // and collision-free across repeated provisioning attempts.
        $gatewayPlanId = 'swado_plan_' . $plan->id;

        $response = $this->http()->post($this->baseUrl() . '/pg/plans', [
            'plan_id' => $gatewayPlanId,
            'plan_name' => $plan->name,
            'plan_type' => 'PERIODIC',
            'plan_currency' => $plan->currency ?: 'INR',
            'plan_recurring_amount' => (float) $plan->price,
            'plan_max_amount' => (float) $plan->price,
            'plan_max_cycles' => 120,
            'plan_interval_type' => $intervalType,
            'plan_intervals' => $intervals,
            'plan_note' => $plan->description,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Cashfree plan: ' . $response->body());
        }

        $plan->update(['cashfree_plan_id' => $gatewayPlanId]);

        return $gatewayPlanId;
    }

    public function createSubscription(RestaurantSubscription|CustomerSubscription $subscription, string $gatewayCustomerId, string $gatewayPlanId): array
    {
        if ($subscription instanceof RestaurantSubscription) {
            $restaurant = $subscription->restaurant;
            $owner = $restaurant?->owner;
            $customerName = $owner?->name ?? $restaurant?->name ?? 'Restaurant owner';
            $customerPhone = $owner?->phone ?? $restaurant?->phone ?? '';
        } else {
            $user = $subscription->user;
            $customerName = $user?->name ?? 'Customer';
            $customerPhone = $user?->phone ?? '';
        }

        // Cashfree subscription ids are also caller-chosen and must be
        // unique -- our own row id plus a timestamp keeps retries collision-free.
        // Prefixed by table (restaurant vs customer Gold both auto-increment
        // from 1, so their bare ids can collide).
        $entityPrefix = $subscription instanceof RestaurantSubscription ? 'r' : 'c';
        $gatewaySubscriptionId = 'swado_sub_' . $entityPrefix . $subscription->id . '_' . now()->timestamp;

        $payload = [
            'subscription_id' => $gatewaySubscriptionId,
            'plan_details' => ['plan_id' => $gatewayPlanId],
            'customer_details' => [
                'customer_name' => $customerName,
                'customer_email' => $gatewayCustomerId,
                'customer_phone' => $customerPhone,
            ],
            'authorization_details' => [
                // Authorizing a nominal amount now; the recurring charges
                // themselves are each billed at the plan's own amount.
                'authorization_amount' => 1,
            ],
        ];

        if ($subscription->trial_ends_at) {
            $payload['subscription_first_charge_time'] = $subscription->trial_ends_at->toIso8601String();
        }

        $response = $this->http()->post($this->baseUrl() . '/pg/subscriptions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Cashfree subscription: ' . $response->body());
        }

        $data = $response->json();

        return [
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'checkout' => [
                'subscription_id' => $gatewaySubscriptionId,
                'subscription_session_id' => $data['subscription_session_id'] ?? null,
                // Hosted authorization page -- the Flutter app opens this
                // (in-app browser/webview) to complete the mandate, mirroring
                // how Cashfree's one-off checkout is a hosted page too; no
                // native "subscription" mode exists in the installed
                // flutter_cashfree_pg_sdk version, unlike its one-off
                // CFWebCheckoutPayment flow already used for ad-wallet top-up.
                'authorization_link' => $data['authorization_details']['authorization_link'] ?? null,
                'environment' => AppSetting::getValue('cashfree_mode', 'live') === 'test' ? 'sandbox' : 'production',
            ],
        ];
    }

    public function cancel(RestaurantSubscription|CustomerSubscription $subscription): bool
    {
        if (! $subscription->gateway_subscription_id) {
            return true;
        }

        $response = $this->http()->post(
            $this->baseUrl() . '/pg/subscriptions/' . $subscription->gateway_subscription_id . '/cancel'
        );

        return $response->successful();
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader, ?string $timestampHeader = null): bool
    {
        $webhookSecret = AppSetting::getValue('cashfree_subscription_webhook_secret');
        if (! $webhookSecret || ! $timestampHeader) {
            return false;
        }

        // Cashfree signs (timestamp + rawBody) with the webhook secret,
        // base64-encoded -- distinct scheme from Razorpay's hex HMAC of the
        // body alone.
        $expected = base64_encode(hash_hmac('sha256', $timestampHeader . $rawBody, $webhookSecret, true));

        return hash_equals($expected, (string) $signatureHeader);
    }
}
