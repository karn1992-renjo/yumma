<?php

namespace App\Payments;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CashfreeGateway implements PaymentGatewayContract
{
    public function createCustomerPayment(Order $order, PaymentAttempt $attempt): array
    {
        $cashfreeOrderId = 'ORDER_' . $order->id . '_' . $attempt->id . '_' . time();
        $response = Http::withHeaders($this->headers())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl() . '/pg/orders', $this->orderPayload($order, $attempt, $cashfreeOrderId));

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Cashfree order.');
        }

        $payload = $response->json();

        return [
            'gateway_reference' => $payload['order_id'] ?? $cashfreeOrderId,
            'payment_link_id' => $payload['order_id'] ?? $cashfreeOrderId,
            'payment_link' => $payload['payment_link'] ?? null,
            'qr_reference' => $payload['payment_link'] ?? null,
            'payload' => $payload,
            'response' => [
                'order_id' => $payload['order_id'] ?? $cashfreeOrderId,
                'payment_session_id' => $payload['payment_session_id'] ?? null,
                'order_token' => $payload['order_token'] ?? null,
                'payment_url' => $payload['payment_link'] ?? null,
                'environment' => $this->isSandbox() ? 'sandbox' : 'production',
            ],
        ];
    }

    public function createPaymentLink(Order $order, PaymentAttempt $attempt): array
    {
        $created = $this->createCustomerPayment($order, $attempt);
        $url = $created['payment_link'] ?? $created['response']['payment_url'] ?? null;

        return array_merge($created, [
            'payment_link' => $url,
            'qr_reference' => $url,
            'response' => array_merge($created['response'], [
                'payment_url' => $url,
                'qr_code' => $url,
            ]),
        ]);
    }

    public function verifyWebhook(Request $request): array
    {
        $timestamp = (string) $request->header('x-webhook-timestamp', '');
        $signature = (string) $request->header('x-webhook-signature', '');
        $secret = $this->clientSecret();
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $request->getContent(), $secret, true));
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Cashfree webhook signature.');
        }

        $payload = $request->json()->all();
        $order = $payload['data']['order'] ?? [];
        $payment = $payload['data']['payment'] ?? [];
        $tags = $order['order_tags'] ?? [];

        return [
            'event_id' => $payload['cf_event_id'] ?? $payload['event_id'] ?? ($payment['cf_payment_id'] ?? null),
            'event' => $payload['type'] ?? null,
            'gateway_reference' => $order['order_id'] ?? null,
            'payment_link_id' => $order['order_id'] ?? null,
            'transaction_id' => $payment['cf_payment_id'] ?? null,
            'payment_attempt_id' => $tags['payment_attempt_id'] ?? null,
            'status' => $payment['payment_status'] ?? $order['order_status'] ?? null,
            'amount' => isset($payment['payment_amount']) ? (float) $payment['payment_amount'] : null,
            'payload' => $payload,
        ];
    }

    public function fetchPaymentForAttempt(PaymentAttempt $attempt): ?array
    {
        return null;
    }

    private function orderPayload(Order $order, PaymentAttempt $attempt, string $cashfreeOrderId): array
    {
        return [
            'order_id' => $cashfreeOrderId,
            'order_amount' => round((float) $attempt->amount, 2),
            'order_currency' => $attempt->currency,
            'order_note' => 'Payment for order #' . $order->order_number,
            'order_expiry_time' => $attempt->expires_at?->toIso8601String(),
            'order_tags' => [
                'order_id' => (string) $order->id,
                'payment_attempt_id' => (string) $attempt->id,
                'source' => (string) $attempt->source,
            ],
            'customer_details' => [
                'customer_id' => 'CUST_' . $order->customer_id,
                'customer_email' => $this->customerEmail($order),
                'customer_phone' => $this->customerPhone($order),
                'customer_name' => $order->customer_name ?: ($order->customer?->name ?: 'Customer'),
            ],
        ];
    }

    private function customerEmail(Order $order): string
    {
        $email = trim((string) ($order->customer?->email ?? ''));

        return $email !== '' ? $email : 'customer' . $order->customer_id . '@foodflow.local';
    }

    private function customerPhone(Order $order): string
    {
        $phone = trim((string) ($order->customer_phone ?: $order->customer?->phone ?: ''));

        return $phone !== '' ? $phone : '9999999999';
    }

    private function headers(): array
    {
        return [
            'x-api-version' => config('services.cashfree.api_version', '2022-09-01'),
            'x-client-id' => $this->clientId(),
            'x-client-secret' => $this->clientSecret(),
        ];
    }

    private function clientId(): string
    {
        $clientId = AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', config('services.cashfree.client_id')));
        if (! $clientId || ! $this->clientSecret()) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        return (string) $clientId;
    }

    private function clientSecret(): string
    {
        $secret = AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', config('services.cashfree.client_secret')));
        if (! $secret) {
            throw new RuntimeException('Cashfree is not configured.');
        }

        return (string) $secret;
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }

    private function isSandbox(): bool
    {
        return AppSetting::getValue('cashfree_mode', 'test') !== 'live';
    }
}
