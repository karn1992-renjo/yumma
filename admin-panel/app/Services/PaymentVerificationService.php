<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * Re-checks a payment's real status directly with the gateway (Razorpay,
 * Cashfree, or Stripe) using whatever reference was stored on the order at
 * creation time, rather than trusting whatever's currently in
 * orders.payment_status. Used by two admin-facing "Verify Payment" actions:
 *
 * 1. An existing order whose payment_status looks stuck/wrong -- re-query
 *    the gateway and correct it.
 * 2. The payment recovery queue -- a payment the gateway confirmed charged
 *    but order creation failed for (see
 *    PaymentController::createOrderFromVerifiedCheckout()'s retry/critical-
 *    log path) -- confirm it's genuinely paid before replaying order
 *    creation from the saved checkout payload.
 *
 * Deliberately read-only: never changes anything at the gateway, only
 * reports what the gateway says. The caller decides what to do with it.
 */
class PaymentVerificationService
{
    /**
     * @return array{paid: bool, amount: ?float, status: string, message: ?string, raw: array}
     */
    public function checkOrderPayment(Order $order): array
    {
        return $this->check(
            gateway: (string) ($order->payment_gateway ?? ''),
            paymentId: (string) ($order->payment_id ?? ''),
            gatewayReference: (string) ($order->payment_link_id ?? ''),
        );
    }

    /**
     * Same check, but for a payment that was confirmed by the client (so we
     * have payment_id/gateway_reference directly from the verification
     * payload) without an Order row to read them from yet -- the recovery
     * queue's use case.
     */
    public function check(string $gateway, string $paymentId, string $gatewayReference = ''): array
    {
        $gateway = strtolower(trim($gateway));

        return match ($gateway) {
            'razorpay' => $this->checkRazorpay($paymentId),
            'cashfree' => $this->checkCashfree($paymentId, $gatewayReference),
            'stripe' => $this->checkStripe($paymentId),
            default => [
                'paid' => false,
                'amount' => null,
                'status' => 'unknown',
                'message' => $gateway === ''
                    ? 'This order has no recorded payment gateway.'
                    : "Unsupported payment gateway: {$gateway}.",
                'raw' => [],
            ],
        };
    }

    private function checkRazorpay(string $paymentId): array
    {
        if ($paymentId === '') {
            return $this->missingReference('Razorpay payment ID');
        }

        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $key || ! $secret) {
            return $this->notConfigured('Razorpay');
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->get("https://api.razorpay.com/v1/payments/{$paymentId}");

        if (! $response->successful()) {
            return [
                'paid' => false,
                'amount' => null,
                'status' => 'lookup_failed',
                'message' => 'Razorpay did not recognise this payment ID: '
                    . ($response->json('error.description') ?? $response->body()),
                'raw' => $response->json() ?: [],
            ];
        }

        $payload = $response->json() ?: [];
        $status = (string) ($payload['status'] ?? 'unknown');
        // Razorpay payment statuses: created, authorized, captured, refunded,
        // failed. Only "captured" means the money actually settled to you.
        $paid = $status === 'captured';

        return [
            'paid' => $paid,
            'amount' => isset($payload['amount']) ? ((float) $payload['amount']) / 100 : null,
            'status' => $status,
            'message' => $paid
                ? 'Razorpay confirms this payment was captured.'
                : "Razorpay reports status \"{$status}\" -- not captured.",
            'raw' => $payload,
        ];
    }

    private function checkCashfree(string $paymentId, string $gatewayReference): array
    {
        // Cashfree's order-status endpoint is keyed by the Cashfree ORDER
        // id, which is stored as orders.payment_link_id (gateway_reference)
        // -- payment_id there is the specific cf_payment_id within that
        // order and isn't independently queryable the same way, so prefer
        // the order reference and fall back to payment_id only if that's
        // all we have (covers older rows saved before payment_link_id was
        // consistently populated).
        $cashfreeOrderId = $gatewayReference !== '' ? $gatewayReference : $paymentId;
        if ($cashfreeOrderId === '') {
            return $this->missingReference('Cashfree order reference');
        }

        $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
        $clientSecret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
        if (! $clientId || ! $clientSecret) {
            return $this->notConfigured('Cashfree');
        }

        $baseUrl = AppSetting::getValue('cashfree_mode', 'test') === 'test'
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
        $headers = [
            'x-api-version' => config('services.cashfree.api_version', '2022-09-01'),
            'x-client-id' => $clientId,
            'x-client-secret' => $clientSecret,
        ];

        $orderResponse = Http::withHeaders($headers)->get("{$baseUrl}/pg/orders/{$cashfreeOrderId}");
        if (! $orderResponse->successful()) {
            return [
                'paid' => false,
                'amount' => null,
                'status' => 'lookup_failed',
                'message' => 'Cashfree did not recognise this order reference: ' . $orderResponse->body(),
                'raw' => $orderResponse->json() ?: [],
            ];
        }

        $order = $orderResponse->json() ?: [];
        $status = strtoupper((string) ($order['order_status'] ?? 'UNKNOWN'));
        $paid = $status === 'PAID';

        $amount = $order['order_amount'] ?? null;
        if ($paid) {
            $paymentsResponse = Http::withHeaders($headers)->get("{$baseUrl}/pg/orders/{$cashfreeOrderId}/payments");
            $payments = $paymentsResponse->successful() ? ($paymentsResponse->json() ?: []) : [];
            if (isset($payments['payments'])) {
                $payments = $payments['payments'];
            }
            $successful = collect($payments)->first(
                fn ($p) => strtoupper((string) ($p['payment_status'] ?? '')) === 'SUCCESS'
            );
            $amount = $successful['payment_amount'] ?? $amount;
        }

        return [
            'paid' => $paid,
            'amount' => $amount !== null ? (float) $amount : null,
            'status' => $status,
            'message' => $paid
                ? 'Cashfree confirms this order is PAID.'
                : "Cashfree reports order status \"{$status}\" -- not paid.",
            'raw' => $order,
        ];
    }

    private function checkStripe(string $paymentId): array
    {
        if ($paymentId === '') {
            return $this->missingReference('Stripe payment intent ID');
        }

        $secret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
        if (! $secret) {
            return $this->notConfigured('Stripe');
        }

        try {
            Stripe::setApiKey($secret);
            $intent = PaymentIntent::retrieve($paymentId);
        } catch (\Throwable $e) {
            return [
                'paid' => false,
                'amount' => null,
                'status' => 'lookup_failed',
                'message' => 'Stripe did not recognise this payment intent ID: ' . $e->getMessage(),
                'raw' => [],
            ];
        }

        $paid = $intent->status === 'succeeded';

        return [
            'paid' => $paid,
            'amount' => ((float) $intent->amount) / 100,
            'status' => (string) $intent->status,
            'message' => $paid
                ? 'Stripe confirms this payment succeeded.'
                : "Stripe reports status \"{$intent->status}\" -- not succeeded.",
            'raw' => $intent->toArray(),
        ];
    }

    private function missingReference(string $what): array
    {
        return [
            'paid' => false,
            'amount' => null,
            'status' => 'missing_reference',
            'message' => "No {$what} is recorded to verify against.",
            'raw' => [],
        ];
    }

    private function notConfigured(string $gateway): array
    {
        return [
            'paid' => false,
            'amount' => null,
            'status' => 'not_configured',
            'message' => "{$gateway} is not configured on this server.",
            'raw' => [],
        ];
    }
}
