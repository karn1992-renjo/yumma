<?php

namespace App\Payments;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeGateway implements PaymentGatewayContract
{
    public function createCustomerPayment(Order $order, PaymentAttempt $attempt): array
    {
        Stripe::setApiKey($this->secret());

        $intent = PaymentIntent::create([
            'amount' => (int) round(((float) $attempt->amount) * 100),
            'currency' => strtolower($attempt->currency),
            'metadata' => $this->metadata($order, $attempt),
        ]);

        return [
            'gateway_reference' => $intent->id,
            'payment_link_id' => null,
            'payment_link' => null,
            'qr_reference' => null,
            'payload' => $intent->toArray(),
            'response' => [
                'client_secret' => $intent->client_secret,
                'publishable_key' => AppSetting::getValue('stripe_key', config('services.stripe.key')),
            ],
        ];
    }

    public function createPaymentLink(Order $order, PaymentAttempt $attempt): array
    {
        Stripe::setApiKey($this->secret());

        $session = Session::create([
            'mode' => 'payment',
            'expires_at' => $attempt->expires_at?->timestamp,
            'client_reference_id' => 'order_' . $order->id . '_attempt_' . $attempt->id,
            'metadata' => $this->metadata($order, $attempt),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($attempt->currency),
                    'unit_amount' => (int) round(((float) $attempt->amount) * 100),
                    'product_data' => [
                        'name' => 'Order #' . $order->order_number,
                    ],
                ],
            ]],
            'success_url' => config('app.url') . '/payment/success?attempt=' . $attempt->id,
            'cancel_url' => config('app.url') . '/payment/cancel?attempt=' . $attempt->id,
        ]);

        return [
            'gateway_reference' => $session->id,
            'payment_link_id' => $session->id,
            'payment_link' => $session->url,
            'qr_reference' => $session->url,
            'payload' => $session->toArray(),
            'response' => [
                'session_id' => $session->id,
                'payment_url' => $session->url,
                'qr_code' => $session->url,
            ],
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $secret = AppSetting::getValue('stripe_webhook_secret', config('services.stripe.webhook_secret'));
        if (! $secret) {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $event = Webhook::constructEvent(
            $request->getContent(),
            (string) $request->header('Stripe-Signature', ''),
            $secret
        );

        $object = $event->data->object;
        $metadata = (array) ($object->metadata ?? []);

        return [
            'event_id' => $event->id,
            'event' => $event->type,
            'gateway_reference' => $object->id ?? null,
            'payment_link_id' => $object->id ?? null,
            'transaction_id' => $object->payment_intent ?? $object->id ?? null,
            'payment_attempt_id' => $metadata['payment_attempt_id'] ?? null,
            'status' => $object->payment_status ?? $object->status ?? null,
            'amount' => isset($object->amount_total)
                ? ((float) $object->amount_total / 100)
                : (isset($object->amount) ? ((float) $object->amount / 100) : null),
            'payload' => $event->toArray(),
        ];
    }

    public function fetchPaymentForAttempt(PaymentAttempt $attempt): ?array
    {
        return null;
    }

    private function secret(): string
    {
        $secret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured.');
        }

        return (string) $secret;
    }

    private function metadata(Order $order, PaymentAttempt $attempt): array
    {
        return [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'payment_attempt_id' => (string) $attempt->id,
            'source' => (string) $attempt->source,
        ];
    }
}
