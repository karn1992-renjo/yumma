<?php

namespace App\Payments;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Support\JsonHttp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CashfreeGateway implements PaymentGatewayContract
{
    public function createCustomerPayment(Order $order, PaymentAttempt $attempt): array
    {
        $cashfreeOrderId = 'ORDER_' . $order->id . '_' . $attempt->id . '_' . time();
        $response = JsonHttp::post(
            Http::withHeaders($this->headers())->acceptJson(),
            $this->baseUrl() . '/pg/orders',
            $this->orderPayload($order, $attempt, $cashfreeOrderId)
        );

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
        // Deliberately NOT built on createCustomerPayment() -- that calls the
        // Orders API (/pg/orders), which returns a payment_session_id for
        // Cashfree's checkout SDK, never a shareable link or QR. The driver
        // "customer scans to pay" flow needs Cashfree's separate Payment
        // Links API (/pg/links), which returns link_url and a ready-to-render
        // link_qrcode (a base64 PNG data URI) directly -- no download step.
        $linkId = 'DRVQR_' . $order->id . '_' . $attempt->id . '_' . time();
        $response = JsonHttp::post(
            Http::withHeaders($this->headers())->acceptJson(),
            $this->baseUrl() . '/pg/links',
            $this->linkPayload($order, $attempt, $linkId)
        );

        Log::info('Cashfree payment link create response.', [
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'link_id' => $linkId,
            'base_url' => $this->baseUrl(),
            'http_status' => $response->status(),
            'successful' => $response->successful(),
            'response_keys' => is_array($response->json()) ? array_keys($response->json()) : null,
            'has_link_url' => filled($response->json()['link_url'] ?? null),
            'has_link_qrcode' => filled($response->json()['link_qrcode'] ?? null),
            'link_status' => $response->json()['link_status'] ?? null,
            'raw_body' => $response->successful() ? null : $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Cashfree payment link.');
        }

        $payload = $response->json() ?: [];
        $linkUrl = $payload['link_url'] ?? null;
        $qrDataUri = $payload['link_qrcode'] ?? null;

        $imageBytes = null;
        $imageMimeType = null;
        if (is_string($qrDataUri) && preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $qrDataUri, $matches)) {
            $imageMimeType = $matches[1];
            $imageBytes = $matches[2];
        } elseif (is_string($qrDataUri) && $qrDataUri !== '') {
            Log::warning('Cashfree link_qrcode did not match expected data-URI format.', [
                'order_id' => $order->id,
                'payment_attempt_id' => $attempt->id,
                'link_qrcode_prefix' => substr($qrDataUri, 0, 40),
            ]);
        }

        $linkReference = $payload['link_id'] ?? $linkId;

        return [
            'gateway_reference' => $payload['cf_link_id'] ?? $linkReference,
            'payment_link_id' => $linkReference,
            'payment_link' => $linkUrl,
            // NOT $imageBytes -- qr_reference/qr_code/qr_payload are for a
            // genuine text UPI-intent payload the CLIENT renders into a QR
            // itself (see RazorpayGateway's image_content convention, the
            // only other implementation of this contract). link_qrcode is
            // already a rendered PNG, not text -- putting raw image bytes
            // here would make the driver app's QrImageView(data: <bytes>)
            // encode the base64 STRING into a new, unscannable-as-payment QR
            // instead of displaying the real one via Image.memory(). Must
            // stay null so the app's image_bytes branch is what renders.
            'qr_reference' => null,
            'payload' => $payload,
            'response' => [
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $linkReference,
                'image_url' => null,
                'image_content' => null,
                'image_bytes' => $imageBytes,
                'image_mime_type' => $imageMimeType,
                'image_encoding' => $imageBytes ? 'base64' : null,
                'close_by' => $attempt->expires_at?->timestamp,
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'status' => $payload['link_status'] ?? null,
                'payment_url' => $linkUrl,
                'qr_code' => null,
                'qr_payload' => null,
                'qr_image_url' => null,
                'render_mode' => $imageBytes ? 'image_bytes' : 'payload',
            ],
        ];
    }

    public function verifyWebhook(Request $request): array
    {
        $timestamp = (string) $request->header('x-webhook-timestamp', '');
        $signature = (string) $request->header('x-webhook-signature', '');
        $secret = $this->clientSecret();
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $request->getContent(), $secret, true));
        $signatureValid = $signature !== '' && hash_equals($expected, $signature);

        Log::info('Cashfree webhook received.', [
            'has_timestamp_header' => $timestamp !== '',
            'has_signature_header' => $signature !== '',
            'signature_valid' => $signatureValid,
            'content_length' => strlen($request->getContent()),
            'raw_type' => $request->json('type'),
        ]);

        if (! $signatureValid) {
            throw new RuntimeException('Invalid Cashfree webhook signature.');
        }

        $payload = $request->json()->all();
        $type = (string) ($payload['type'] ?? '');

        // Payment Links (driver QR, see createPaymentLink()) fire a
        // differently-shaped webhook (type=PAYMENT_LINK_EVENT, data.link_id /
        // data.order.transaction_status) than the Orders API's webhook
        // (data.order / data.payment) used by customer checkout -- these are
        // two separate Cashfree products with separate payload schemas.
        $parsed = $type === 'PAYMENT_LINK_EVENT'
            ? $this->parsePaymentLinkWebhook($payload)
            : $this->parseOrderWebhook($payload);

        Log::info('Cashfree webhook parsed.', [
            'type' => $type,
            'parsed_as' => $type === 'PAYMENT_LINK_EVENT' ? 'payment_link' : 'order',
            'event_id' => $parsed['event_id'] ?? null,
            'gateway_reference' => $parsed['gateway_reference'] ?? null,
            'payment_link_id' => $parsed['payment_link_id'] ?? null,
            'transaction_id' => $parsed['transaction_id'] ?? null,
            'payment_attempt_id' => $parsed['payment_attempt_id'] ?? null,
            'status' => $parsed['status'] ?? null,
            'amount' => $parsed['amount'] ?? null,
        ]);

        return $parsed;
    }

    private function parseOrderWebhook(array $payload): array
    {
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

    private function parsePaymentLinkWebhook(array $payload): array
    {
        $data = $payload['data'] ?? [];
        $order = $data['order'] ?? [];
        $notes = $data['link_notes'] ?? [];

        return [
            'event_id' => $order['transaction_id'] ?? ($data['cf_link_id'] ?? null),
            'event' => $payload['type'] ?? null,
            'gateway_reference' => $data['link_id'] ?? null,
            'payment_link_id' => $data['link_id'] ?? null,
            'transaction_id' => $order['transaction_id'] ?? null,
            'payment_attempt_id' => $notes['payment_attempt_id'] ?? null,
            'status' => $order['transaction_status'] ?? $data['link_status'] ?? null,
            'amount' => isset($order['order_amount']) ? (float) $order['order_amount'] : null,
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
            // (float) cast is load-bearing -- see App\Support\JsonHttp.
            // Cashfree rejects a bare-integer order_amount for a
            // whole-rupee total, and round() alone doesn't guarantee the
            // JSON encoder emits a decimal point for a whole-number float.
            'order_amount' => (float) round((float) $attempt->amount, 2),
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

    private function linkPayload(Order $order, PaymentAttempt $attempt, string $linkId): array
    {
        return [
            'link_id' => $linkId,
            // (float) cast is load-bearing -- see App\Support\JsonHttp and the
            // matching comment on orderPayload() above.
            'link_amount' => (float) round((float) $attempt->amount, 2),
            'link_currency' => $attempt->currency,
            'link_purpose' => 'Payment for order #' . $order->order_number,
            'link_expiry_time' => $attempt->expires_at?->toIso8601String(),
            'link_notes' => [
                'order_id' => (string) $order->id,
                'payment_attempt_id' => (string) $attempt->id,
                'source' => (string) $attempt->source,
            ],
            'customer_details' => [
                'customer_phone' => $this->customerPhone($order),
                'customer_email' => $this->customerEmail($order),
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
