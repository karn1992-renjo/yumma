<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\RestaurantSubscription;
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
 *
 * Restaurant subscriptions only -- Customer Gold membership is pay-as-you-go
 * (one-time payment, verified synchronously by the client right after
 * checkout) and never touches this or any other webhook. See
 * GoldSubscriptionService::verifyAndActivate().
 */
class RazorpaySubscriptionWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpaySubscriptionGateway $gateway, SubscriptionService $subscriptionService)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        // Distinguished from a genuine signature mismatch below -- an admin
        // who never pasted the webhook secret into Settings -> Payment (or
        // never registered this URL in Razorpay's dashboard) would otherwise
        // see every webhook rejected as a "mismatch", which reads like a
        // signing bug rather than the real, much simpler cause: every real
        // subscription silently stuck in pending_authorization forever.
        if (! \App\Models\AppSetting::getValue('razorpay_subscription_webhook_secret')) {
            Log::warning('Rejected Razorpay subscription webhook: razorpay_subscription_webhook_secret is not set in Settings -> Payment. Every Razorpay subscription will stay stuck in pending_authorization until this is configured and the webhook URL is registered in Razorpay\'s dashboard.');

            return response()->json(['success' => false], 400);
        }

        if (! $gateway->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Rejected Razorpay subscription webhook: signature mismatch (secret IS configured -- check it matches the one in Razorpay\'s dashboard for this exact webhook URL).');

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
                // Authenticated/activated arrive before any charge: they start a free trial (access straight away).
                'subscription.authenticated', 'subscription.activated' => $subscriptionService->markAuthorized($subscription),
                'subscription.charged' => $subscriptionService->activateFromCharge($subscription, $chargeData),
                'subscription.pending' => $subscriptionService->markPastDue($subscription, 'Razorpay reported a pending/failed renewal charge.'),
                'subscription.halted' => $subscriptionService->markBillingChoiceRequired($subscription),
                'subscription.cancelled' => $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]),
                default => null,
            };

            return response()->json(['success' => true]);
        }

        Log::warning('Razorpay subscription webhook for unknown subscription.', ['gateway_subscription_id' => $gatewaySubscriptionId]);

        return response()->json(['success' => true]);
    }
}
