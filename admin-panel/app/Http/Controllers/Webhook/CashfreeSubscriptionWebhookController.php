<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\RestaurantSubscription;
use App\Services\GoldSubscriptionService;
use App\Services\Subscription\CashfreeSubscriptionGateway;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cashfree Subscriptions webhook -- a separate event stream from the
 * existing /webhook/{gateway} (one-off order payments) and
 * webhooks/cashfree/payout (vendor payout settlement) endpoints. Configure
 * this URL in Cashfree's dashboard against Subscription events, with its
 * own webhook secret stored as cashfree_subscription_webhook_secret.
 */
class CashfreeSubscriptionWebhookController extends Controller
{
    public function __invoke(Request $request, CashfreeSubscriptionGateway $gateway, SubscriptionService $subscriptionService, GoldSubscriptionService $goldService)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('x-webhook-signature', '');
        $timestamp = $request->header('x-webhook-timestamp', '');

        if (! $gateway->verifyWebhookSignature($rawBody, $signature, $timestamp)) {
            Log::warning('Rejected Cashfree subscription webhook: signature mismatch.');

            return response()->json(['success' => false], 400);
        }

        $payload = json_decode($rawBody, true) ?: [];
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];
        $gatewaySubscriptionId = $data['subscription']['subscription_id']
            ?? $data['subscription_id']
            ?? null;

        if (! $type || ! $gatewaySubscriptionId) {
            return response()->json(['success' => true]); // Nothing actionable; ack so Cashfree stops retrying.
        }

        $chargeData = [
            'amount' => $data['payment']['payment_amount'] ?? null,
            'currency' => $data['payment']['payment_currency'] ?? null,
            'payment_id' => $data['payment']['cf_payment_id'] ?? null,
        ];

        // Cashfree's subscription event names (per their Subscriptions API):
        // SUBSCRIPTION_STATUS_CHANGE (status in payload -> ACTIVE/CANCELLED/...)
        // and SUBSCRIPTION_PAYMENT_* for each cycle's charge outcome.
        $subscription = RestaurantSubscription::where('gateway_subscription_id', $gatewaySubscriptionId)->first();
        if ($subscription) {
            match ($type) {
                'SUBSCRIPTION_PAYMENT_SUCCESS', 'SUBSCRIPTION_PAYMENT_CHARGED' => $subscriptionService->activateFromCharge($subscription, $chargeData),
                'SUBSCRIPTION_PAYMENT_FAILED' => $subscriptionService->markPastDue($subscription, 'Cashfree reported a failed renewal charge.'),
                'SUBSCRIPTION_STATUS_CHANGE' => $this->handleRestaurantStatusChange($subscription, $data, $subscriptionService),
                default => null,
            };

            return response()->json(['success' => true]);
        }

        $goldSubscription = CustomerSubscription::where('gateway_subscription_id', $gatewaySubscriptionId)->first();
        if ($goldSubscription) {
            match ($type) {
                'SUBSCRIPTION_PAYMENT_SUCCESS', 'SUBSCRIPTION_PAYMENT_CHARGED' => $goldService->activateFromCharge($goldSubscription, $chargeData),
                'SUBSCRIPTION_PAYMENT_FAILED' => $goldService->markPastDue($goldSubscription, 'Cashfree reported a failed renewal charge.'),
                'SUBSCRIPTION_STATUS_CHANGE' => $this->handleGoldStatusChange($goldSubscription, $data, $goldService),
                default => null,
            };

            return response()->json(['success' => true]);
        }

        Log::warning('Cashfree subscription webhook for unknown subscription.', ['gateway_subscription_id' => $gatewaySubscriptionId]);

        return response()->json(['success' => true]);
    }

    private function handleRestaurantStatusChange(RestaurantSubscription $subscription, array $data, SubscriptionService $subscriptionService): void
    {
        $status = strtoupper((string) ($data['subscription']['subscription_status'] ?? $data['subscription_status'] ?? ''));

        match ($status) {
            'CANCELLED' => $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]),
            'ON_HOLD', 'COMPLETED' => $subscriptionService->markBillingChoiceRequired($subscription),
            default => null,
        };
    }

    private function handleGoldStatusChange(CustomerSubscription $subscription, array $data, GoldSubscriptionService $goldService): void
    {
        $status = strtoupper((string) ($data['subscription']['subscription_status'] ?? $data['subscription_status'] ?? ''));

        match ($status) {
            'CANCELLED' => $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]),
            'ON_HOLD', 'COMPLETED' => $goldService->markExpired($subscription),
            default => null,
        };
    }
}
