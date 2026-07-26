<?php

namespace App\Payments;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RazorpayGateway implements PaymentGatewayContract
{
    public function createCustomerPayment(Order $order, PaymentAttempt $attempt): array
    {
        $response = Http::withBasicAuth($this->key(), $this->secret())
            ->acceptJson()
            ->asJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'receipt' => 'cod_' . $order->order_number . '_' . $attempt->id,
                'amount' => (int) round(((float) $attempt->amount) * 100),
                'currency' => $attempt->currency,
                'payment_capture' => 1,
                'notes' => $this->notes($order, $attempt),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to create Razorpay order.');
        }

        $payload = $response->json();

        return [
            'gateway_reference' => $payload['id'] ?? null,
            'payment_link_id' => null,
            'payment_link' => null,
            'qr_reference' => null,
            'payload' => $payload,
            'response' => [
                'order_id' => $payload['id'] ?? null,
                'amount_minor' => $payload['amount'] ?? (int) round(((float) $attempt->amount) * 100),
                'currency' => $payload['currency'] ?? $attempt->currency,
                'key' => $this->key(),
            ],
        ];
    }

    public function createPaymentLink(Order $order, PaymentAttempt $attempt): array
    {
        $requestPayload = [
            'type' => 'upi_qr',
            'name' => 'Order ' . $order->order_number,
            'usage' => 'single_use',
            'fixed_amount' => true,
            'payment_amount' => (int) round(((float) $attempt->amount) * 100),
            'description' => 'Payment for order #' . $order->order_number,
            'close_by' => $this->expireBy($attempt),
            'notes' => $this->notes($order, $attempt),
        ];

        $response = Http::withBasicAuth($this->key(), $this->secret())
            ->acceptJson()
            ->asJson()
            ->post('https://api.razorpay.com/v1/payments/qr_codes', $requestPayload);

        if (! $response->successful()) {
            $this->throwGatewayException($response, $requestPayload, $order, $attempt);
        }

        $payload = $response->json() ?: [];
        $qrId = $payload['id'] ?? null;
        if (! is_string($qrId) || ! str_starts_with($qrId, 'qr_')) {
            Log::warning('Razorpay Dynamic QR response missing QR ID.', [
                'order_id' => $order->id,
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $qrId,
                'request' => $this->safePayloadForLog($requestPayload),
                'response' => $this->safePayloadForLog($payload),
            ]);

            throw new RuntimeException('Razorpay did not return a valid Dynamic QR ID.');
        }

        $imageContent = $payload['image_content'] ?? null;
        $imageUrl = $payload['image_url'] ?? null;
        $imageBytes = null;
        $imageMimeType = null;
        if ((! is_string($imageContent) || trim($imageContent) === '') && is_string($imageUrl) && trim($imageUrl) !== '') {
            $downloadedImage = $this->downloadQrImage($imageUrl, $order, $attempt, $qrId);
            $imageBytes = $downloadedImage['bytes'];
            $imageMimeType = $downloadedImage['mime_type'];
        }

        Log::info('Razorpay Dynamic QR created.', [
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'qr_id' => $qrId,
            'image_url' => $imageUrl,
            'has_image_content' => is_string($imageContent) && trim($imageContent) !== '',
            'has_image_bytes' => is_string($imageBytes),
            'status' => $payload['status'] ?? null,
        ]);

        return [
            'gateway_reference' => $qrId,
            'payment_link_id' => $qrId,
            'payment_link' => null,
            'qr_reference' => is_string($imageContent) ? $imageContent : null,
            'payload' => $payload,
            'response' => [
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $qrId,
                'image_url' => is_string($imageUrl) ? $imageUrl : null,
                'image_content' => is_string($imageContent) ? $imageContent : null,
                'image_bytes' => is_string($imageBytes) ? $imageBytes : null,
                'image_mime_type' => $imageMimeType,
                'image_encoding' => is_string($imageBytes) ? 'base64' : null,
                'close_by' => $payload['close_by'] ?? $requestPayload['close_by'],
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'status' => $payload['status'] ?? null,
                'payment_url' => null,
                'qr_code' => is_string($imageContent) ? $imageContent : null,
                'qr_payload' => is_string($imageContent) ? $imageContent : null,
                'qr_image_url' => is_string($imageUrl) ? $imageUrl : null,
                'render_mode' => is_string($imageContent) && trim($imageContent) !== '' ? 'payload' : 'image_bytes',
            ],
        ];
    }

    public function preparePaymentAttemptForDisplay(PaymentAttempt $attempt): void
    {
        if ($attempt->source !== PaymentAttempt::SOURCE_DRIVER_QR) {
            return;
        }

        $payload = $attempt->gateway_payload;
        if (! is_array($payload)) {
            return;
        }

        $client = $payload['client'] ?? [];
        if (! is_array($client)) {
            return;
        }

        $hasLocalPayload = is_string($client['image_content'] ?? null) && trim((string) $client['image_content']) !== '';
        $hasImageBytes = is_string($client['image_bytes'] ?? null) && trim((string) $client['image_bytes']) !== '';
        $imageUrl = $client['image_url'] ?? $client['qr_image_url'] ?? null;
        if ($hasLocalPayload || $hasImageBytes || ! is_string($imageUrl) || trim($imageUrl) === '') {
            return;
        }

        $order = $attempt->order ?: Order::find($attempt->order_id);
        if (! $order) {
            return;
        }

        $qrId = (string) ($client['qr_id'] ?? $attempt->gateway_reference);
        $downloadedImage = $this->downloadQrImage($imageUrl, $order, $attempt, $qrId);
        $client['image_bytes'] = $downloadedImage['bytes'];
        $client['image_mime_type'] = $downloadedImage['mime_type'];
        $client['image_encoding'] = 'base64';
        $client['render_mode'] = 'image_bytes';
        $client['payment_url'] = null;
        $client['qr_code'] = $client['image_content'] ?? null;
        $payload['client'] = $client;

        $attempt->forceFill(['gateway_payload' => $payload])->save();

        Log::info('Razorpay QR display payload hydrated.', [
            'order_id' => $attempt->order_id,
            'payment_attempt_id' => $attempt->id,
            'qr_id' => $qrId,
            'image_url' => $imageUrl,
            'image_mime_type' => $client['image_mime_type'],
            'image_bytes_length' => strlen((string) $client['image_bytes']),
        ]);
    }
    public function verifyWebhook(Request $request): array
    {
        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $this->webhookSecret());
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid Razorpay webhook signature.');
        }

        $payload = $request->json()->all();
        $entity = $payload['payload']['payment']['entity']
            ?? $payload['payload']['payment_link']['entity']
            ?? $payload['payload']['qr_code']['entity']
            ?? [];
        $paymentLink = $payload['payload']['payment_link']['entity'] ?? [];
        $qrCode = $payload['payload']['qr_code']['entity'] ?? [];
        $notes = $this->firstArrayValue(
            $entity['notes'] ?? null,
            $qrCode['notes'] ?? null,
            $paymentLink['notes'] ?? null,
        );
        $transactionId = $entity['entity'] ?? null;
        $transactionId = $transactionId === 'payment' ? ($entity['id'] ?? null) : null;
        $qrId = $qrCode['id']
            ?? $entity['qr_code_id']
            ?? $entity['qr_id']
            ?? $entity['receiver_id']
            ?? null;
        $gatewayReference = $entity['order_id']
            ?? $qrId
            ?? ($paymentLink['id'] ?? null)
            ?? ($entity['id'] ?? null);
        $status = $entity['status'] ?? $paymentLink['status'] ?? $qrCode['status'] ?? null;
        if (($payload['event'] ?? null) === 'qr_code.closed' && ($qrCode['close_reason'] ?? null) === 'paid') {
            $status = 'paid';
        }
        $amount = isset($entity['amount'])
            ? ((float) $entity['amount'] / 100)
            : (isset($qrCode['payment_amount']) ? ((float) $qrCode['payment_amount'] / 100) : null);

        return [
            'event_id' => $payload['id'] ?? ($entity['id'] ?? null),
            'event' => $payload['event'] ?? null,
            'gateway_reference' => $gatewayReference,
            'payment_link_id' => $paymentLink['id'] ?? $entity['payment_link_id'] ?? $qrId,
            'transaction_id' => $transactionId,
            'payment_attempt_id' => $notes['payment_attempt_id'] ?? null,
            'status' => $status,
            'amount' => $amount,
            'payload' => $payload,
        ];
    }

    public function fetchPaymentForAttempt(PaymentAttempt $attempt): ?array
    {
        if ($attempt->source !== PaymentAttempt::SOURCE_DRIVER_QR || ! str_starts_with((string) $attempt->gateway_reference, 'qr_')) {
            return null;
        }

        $response = Http::withBasicAuth($this->key(), $this->secret())
            ->acceptJson()
            ->get('https://api.razorpay.com/v1/payments/qr_codes/' . $attempt->gateway_reference . '/payments', [
                'count' => 10,
            ]);

        if (! $response->successful()) {
            Log::warning('Razorpay QR payment fetch failed.', [
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $attempt->gateway_reference,
                'status' => $response->status(),
                'response' => $response->json() ?: $response->body(),
            ]);

            return null;
        }

        $payload = $response->json();
        foreach (($payload['items'] ?? []) as $payment) {
            $status = strtolower((string) ($payment['status'] ?? ''));
            $amount = isset($payment['amount']) ? ((float) $payment['amount'] / 100) : null;
            if ($status !== 'captured' || $amount === null || round($amount, 2) !== round((float) $attempt->amount, 2)) {
                continue;
            }

            return [
                'event_id' => 'poll_' . ($payment['id'] ?? $attempt->gateway_reference),
                'event' => 'payment.captured',
                'gateway_reference' => $attempt->gateway_reference,
                'payment_link_id' => $attempt->payment_link_id,
                'transaction_id' => $payment['id'] ?? null,
                'payment_attempt_id' => $attempt->id,
                'status' => $payment['status'] ?? null,
                'amount' => $amount,
                'payload' => [
                    'source' => 'razorpay_qr_poll',
                    'qr_code_id' => $attempt->gateway_reference,
                    'payment' => $payment,
                    'collection' => $payload,
                ],
            ];
        }

        return null;
    }

    private function key(): string
    {
        $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $key || ! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return (string) $key;
    }

    private function secret(): string
    {
        $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
        if (! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return (string) $secret;
    }

    private function webhookSecret(): string
    {
        $secret = AppSetting::getValue('razorpay_webhook_secret', config('services.razorpay.webhook_secret'));
        if (! $secret) {
            throw new RuntimeException('Razorpay webhook secret is not configured.');
        }

        return (string) $secret;
    }

    private function notes(Order $order, PaymentAttempt $attempt): array
    {
        return [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'payment_attempt_id' => (string) $attempt->id,
            'source' => (string) $attempt->source,
        ];
    }

    private function referenceId(Order $order, PaymentAttempt $attempt): string
    {
        return substr('o' . $order->id . 'a' . $attempt->id, 0, 40);
    }

    private function expireBy(PaymentAttempt $attempt): int
    {
        $minimum = now()->addMinutes(3)->timestamp;
        $maximum = now()->addMinutes(120)->timestamp;
        $attemptExpiry = $attempt->expires_at?->timestamp ?? $minimum;

        return min(max($attemptExpiry, $minimum), $maximum);
    }

    private function customerPayload(Order $order): array
    {
        $customer = [
            'name' => trim((string) ($order->customer_name ?: $order->customer?->name ?: 'Customer')),
        ];

        $contact = $this->razorpayContact((string) ($order->customer_phone ?: $order->customer?->phone));
        if ($contact !== null) {
            $customer['contact'] = $contact;
        }

        $email = trim((string) ($order->customer?->email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $customer['email'] = $email;
        }

        return $customer;
    }

    private function razorpayContact(string $phone): ?string
    {
        $contact = preg_replace('/[^\d+]/', '', trim($phone)) ?: '';
        if (str_starts_with($contact, '++')) {
            $contact = '+' . ltrim($contact, '+');
        }

        $digits = preg_replace('/\D/', '', $contact) ?: '';
        if (strlen($digits) === 10) {
            $countryCode = preg_replace('/\D/', '', AppSetting::getValue('default_mobile_country_code', '+91')) ?: '91';
            $contact = '+' . $countryCode . $digits;
        } elseif ($contact !== '' && ! str_starts_with($contact, '+') && strlen($digits) >= 8 && strlen($digits) <= 14) {
            $contact = $digits;
        }

        $length = strlen($contact);
        return $length >= 8 && $length <= 14 ? $contact : null;
    }

    private function downloadQrImage(string $imageUrl, Order $order, PaymentAttempt $attempt, string $qrId, int $depth = 0): array
    {
        if ($depth > 3) {
            throw new RuntimeException('Razorpay QR image redirects could not be resolved.');
        }

        if (! filter_var($imageUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($imageUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('Razorpay returned an invalid QR image URL.');
        }

        $response = Http::timeout(10)
            ->accept('image/*,*/*')
            ->withOptions([
                'allow_redirects' => [
                    'max' => 5,
                    'track_redirects' => true,
                ],
            ])
            ->get($imageUrl);

        $redirectHistory = $response->header('X-Guzzle-Redirect-History');
        $redirectStatuses = $response->header('X-Guzzle-Redirect-Status-History');
        $contentType = trim((string) ($response->header('Content-Type') ?: ''));
        Log::info('Razorpay QR image fetch response.', [
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'qr_id' => $qrId,
            'image_url' => $imageUrl,
            'status' => $response->status(),
            'content_type' => $contentType,
            'redirect_history' => $redirectHistory,
            'redirect_statuses' => $redirectStatuses,
            'body_bytes' => strlen($response->body()),
        ]);

        if (! $response->successful()) {
            Log::warning('Razorpay QR image download failed.', [
                'order_id' => $order->id,
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $qrId,
                'status' => $response->status(),
                'content_type' => $contentType,
                'image_url' => $imageUrl,
            ]);

            throw new RuntimeException('Razorpay created the QR but the QR image could not be downloaded server-side.');
        }

        $body = $response->body();
        $mimeType = $this->imageMimeType($body, $contentType);
        if ($mimeType === null) {
            $htmlRedirectUrl = $this->htmlRedirectUrl($body);
            if ($htmlRedirectUrl) {
                Log::info('Razorpay QR image URL returned HTML redirect.', [
                    'order_id' => $order->id,
                    'payment_attempt_id' => $attempt->id,
                    'qr_id' => $qrId,
                    'image_url' => $imageUrl,
                    'redirect_url' => $htmlRedirectUrl,
                    'content_type' => $contentType,
                ]);

                return $this->downloadQrImage($htmlRedirectUrl, $order, $attempt, $qrId, $depth + 1);
            }

            Log::warning('Razorpay QR image response was not an image.', [
                'order_id' => $order->id,
                'payment_attempt_id' => $attempt->id,
                'qr_id' => $qrId,
                'image_url' => $imageUrl,
                'status' => $response->status(),
                'content_type' => $contentType,
                'body_preview' => substr($body, 0, 160),
            ]);

            throw new RuntimeException('Razorpay QR image URL returned HTML instead of an image.');
        }

        if ($body === '' || strlen($body) > 2 * 1024 * 1024) {
            throw new RuntimeException('Razorpay QR image response was empty or too large.');
        }

        Log::info('Razorpay QR image downloaded server-side.', [
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'qr_id' => $qrId,
            'mime_type' => $mimeType,
            'bytes' => strlen($body),
        ]);

        return [
            'bytes' => base64_encode($body),
            'mime_type' => $mimeType,
        ];
    }

    private function imageMimeType(string $body, string $contentType): ?string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0]));
        if (str_starts_with($contentType, 'image/')) {
            return $contentType;
        }

        if (str_starts_with($body, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'image/gif';
        }

        return null;
    }

    private function htmlRedirectUrl(string $body): ?string
    {
        if (! preg_match('/href=["\']([^"\']+)["\']/i', $body, $matches)) {
            return null;
        }

        $url = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
    private function throwGatewayException($response, array $payload, Order $order, PaymentAttempt $attempt): never
    {
        $body = $response->json();
        $message = $body['error']['description']
            ?? $body['error']['reason']
            ?? $body['message']
            ?? $response->body()
            ?? 'Unable to create Razorpay QR.';

        Log::warning('Razorpay QR creation failed.', [
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'status' => $response->status(),
            'message' => $message,
            'request' => $this->safePayloadForLog($payload),
            'response' => $body ?: $response->body(),
        ]);

        throw new RuntimeException('Unable to create Razorpay QR: ' . $message);
    }

    private function firstArrayValue(mixed ...$values): array
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    private function safePayloadForLog(array $payload): array
    {
        if (isset($payload['customer']['contact'])) {
            $payload['customer']['contact'] = '***' . substr((string) $payload['customer']['contact'], -4);
        }

        if (isset($payload['customer']['email'])) {
            $payload['customer']['email'] = '***';
        }

        return $payload;
    }
}
