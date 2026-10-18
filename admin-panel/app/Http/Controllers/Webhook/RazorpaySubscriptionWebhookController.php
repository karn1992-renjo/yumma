<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\RestaurantSubscription;
use App\Services\GoldSubscriptionService;
use App\Services\SubscriptionService;
use App\Services\Subscription\RazorpaySubscriptionGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay Subscriptions webhook -- a SEPARATE product/event stream from the
 * existing /webhook/{gateway} (one-off order payments) and
 * webhooks/razorpay/payout (vendor payout settlement) endpoints. Configure
 * this URL in the Razorpay dashboard against the "Subscriptions" event
 * group, with its own webhook secret stored as the
 * razorpay_subscription_webhook_secret AppSetting (never the same secret as
 * the other Razorpay webhooks -- each webhook endpoint gets its own in
 * Razorpay's dashboard).
 */
class RazorpaySubscriptionWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpaySubscriptionGateway $gateway, SubscriptionService $subscriptionService, GoldSubscriptionService $goldService)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $gateway->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Rejected Razorpay subscription webhook: signature mismatch.');

            return response()->json(['success' => false], 400);
        }

        $payload = json_decode($rawBody, true) ?: [];
        $event = $payload['event'] ?? null;
        $gatewaySubscriptionId = $payload['payload']['subscription']['entity']['id'] ?? null;

        if (! $event || ! $gatewaySubscriptionId) {
            return response()->json(['success' => true]); // Nothing actionable; ack so Razorpay stops retrying.
        }

        $chargeData = [
            'amount' => isset($payload['payload']['payment']['entity']['amount'])
                ? $payload['payload']['payment']['entity']['amount'] / 100
                : null,
            'currency' => $payload['payload']['payment']['entity']['currency'] ?? null,
            'payment_id' => $payload['payload']['payment']['entity']['id'] ?? null,
        ];

        $subscription = RestaurantSubscription::where('gateway_subscription_id', $gatewaySubscriptionId)->first();
        if ($subscription) {
            match ($event) {
                'subscription.charged' => $subscriptionService->activateFromCharge($subscription, $chargeData),
                'subscription.pending' => $subscriptionService->markPastDue($subscription, 'Razorpay reported a pending/failed renewal charge.'),
                'subscription.halted' => $subscriptionService->markBillingChoiceRequired($subscription),
                'subscription.cancelled' => $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]),
                default => null,
            };

            return response()->json(['success' => true]);
        }

        $goldSubscription = CustomerSubscription::where('gateway_subscription_id', $gatewaySubscriptionId)->first();
        if ($goldSubscription) {
            match ($event) {
                'subscription.charged' => $goldService->activateFromCharge($goldSubscription, $chargeData),
                'subscription.pending' => $goldService->markPastDue($goldSubscription, 'Razorpay reported a pending/failed renewal charge.'),
                'subscription.halted' => $goldService->markExpired($goldSubscription),
                'subscription.cancelled' => $goldSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]),
                default => null,
            };

            return response()->json(['success' => true]);
        }

        Log::warning('Razorpay subscription webhook for unknown subscription.', ['gateway_subscription_id' => $gatewaySubscriptionId]);

        return response()->json(['success' => true]);
    }
}
