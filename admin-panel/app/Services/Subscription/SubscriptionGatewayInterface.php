<?php

namespace App\Services\Subscription;

use App\Models\CustomerSubscription;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;

/**
 * A payment gateway capable of true recurring billing (saved mandate / token,
 * auto-charged by the gateway itself rather than by our own scheduler). Kept
 * separate from the one-off order/ad-wallet payment flows, which only ever
 * create a single Order/PaymentIntent and have no recurring concept.
 */
interface SubscriptionGatewayInterface
{
    /**
     * True when real API credentials are present for this gateway (key +
     * secret both non-empty) -- checked without throwing, so callers can
     * filter which gateways to even offer a subscriber before attempting a
     * real API call.
     */
    public function isConfigured(): bool;

    /**
     * Ensure a gateway-side customer exists for this subscriber, returning
     * its gateway customer id.
     */
    public function resolveCustomerId(string $name, string $email, ?string $phone, ?string $existingGatewayCustomerId): string;

    /**
     * Ensure a gateway-side recurring plan exists for this SubscriptionPlan,
     * returning its gateway plan id. Idempotent -- safe to call every time,
     * it only creates on the gateway once and caches the id.
     */
    public function resolvePlanId(SubscriptionPlan $plan): string;

    /**
     * Create the gateway-side subscription object and return everything the
     * client needs to open the gateway's checkout/authorization UI. Does NOT
     * charge anything yet -- the first charge (or trial-deferred first
     * charge) happens on the gateway's own schedule once authorized.
     *
     * @return array{gateway_subscription_id: string, checkout: array<string, mixed>}
     */
    public function createSubscription(RestaurantSubscription|CustomerSubscription $subscription, string $gatewayCustomerId, string $gatewayPlanId): array;

    public function cancel(RestaurantSubscription|CustomerSubscription $subscription): bool;

    /**
     * Verifies a webhook payload's signature. Returns false on mismatch --
     * callers must reject the request without processing it. $timestampHeader
     * is used by gateways whose signature is computed over
     * (timestamp + body) rather than the body alone (e.g. Cashfree); ignored
     * by gateways that don't need it (e.g. Razorpay).
     */
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader, ?string $timestampHeader = null): bool;
}
