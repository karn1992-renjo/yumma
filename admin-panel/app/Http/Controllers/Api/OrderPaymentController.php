<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\OrderPaymentService;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderPaymentController extends Controller
{
    public function __construct(private readonly OrderPaymentService $payments)
    {
    }

    public function pay(Request $request, int $id)
    {
        $validated = $request->validate([
            'gateway' => ['nullable', Rule::in(['razorpay', 'cashfree', 'stripe'])],
        ]);

        $order = Order::where('customer_id', $request->user()->id)->findOrFail($id);
        $this->ensureCustomerPaymentsEnabled();
        $attempt = $this->payments->createCustomerPayment(
            $order,
            $validated['gateway'] ?? $this->defaultCustomerGateway(),
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data' => $this->creationPayload($attempt),
        ]);
    }

    public function driverPaymentLink(Request $request, int $id)
    {
        $validated = $request->validate([
            'gateway' => ['nullable', Rule::in(['razorpay', 'cashfree', 'stripe'])],
        ]);

        $order = Order::where('driver_id', $request->user()->id)->findOrFail($id);
        $attempt = $this->payments->createDriverPaymentLink(
            $order,
            $validated['gateway'] ?? $this->defaultDriverGateway(),
            $request->user()->id
        );

        $payload = $this->creationPayload($attempt);

        Log::info('Driver payment QR API response prepared.', [
            'backend_url' => $request->fullUrl(),
            'order_id' => $order->id,
            'payment_attempt_id' => $payload['payment_attempt_id'] ?? $attempt->id,
            'qr_id' => $payload['qr_id'] ?? null,
            'status' => $payload['status'] ?? null,
            'image_url' => $payload['image_url'] ?? null,
            'has_image_content' => filled($payload['image_content'] ?? null),
            'image_content_length' => strlen((string) ($payload['image_content'] ?? '')),
            'has_image_bytes' => filled($payload['image_bytes'] ?? null),
            'image_bytes_length' => strlen((string) ($payload['image_bytes'] ?? '')),
            'image_mime_type' => $payload['image_mime_type'] ?? null,
            'render_mode' => $payload['render_mode'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function driverCash(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'collection_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = Order::where('driver_id', $request->user()->id)->findOrFail($id);
        $attempt = $this->payments->markDriverCashReceived(
            $order,
            (float) $validated['amount'],
            $validated['collection_notes'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cash payment collected successfully.',
            'data' => $this->payments->statusPayload($attempt->order),
        ]);
    }

    public function status(Request $request, int $id)
    {
        $order = Order::query()
            ->whereKey($id)
            ->where(function ($query) use ($request) {
                $query->where('customer_id', $request->user()->id)
                    ->orWhere('driver_id', $request->user()->id)
                    ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('owner_id', $request->user()->id));
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->payments->statusPayload($order),
        ]);
    }

    private function creationPayload(PaymentAttempt $attempt): array
    {
        $client = $attempt->gateway_payload['client'] ?? [];
        $client = is_array($client) ? $client : [];

        if ($attempt->source === PaymentAttempt::SOURCE_DRIVER_QR) {
            return [
                'payment_attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
                'gateway' => $attempt->gateway,
                'source' => $attempt->source,
                'amount' => (float) $attempt->amount,
                'currency' => $attempt->currency,
                'session_id' => $client['qr_id'] ?? $attempt->gateway_reference,
                'qr_id' => $client['qr_id'] ?? $attempt->gateway_reference,
                'image_url' => $client['image_url'] ?? $client['qr_image_url'] ?? null,
                'image_content' => $client['image_content'] ?? $attempt->qr_reference,
                'image_bytes' => $client['image_bytes'] ?? null,
                'image_mime_type' => $client['image_mime_type'] ?? null,
                'image_encoding' => $client['image_encoding'] ?? null,
                'qr_payload' => $client['qr_payload'] ?? $client['image_content'] ?? $attempt->qr_reference,
                'qr_code' => $client['qr_payload'] ?? $client['image_content'] ?? $attempt->qr_reference,
                'qr_image_url' => $client['qr_image_url'] ?? $client['image_url'] ?? null,
                'payment_url' => null,
                'close_by' => $client['close_by'] ?? $attempt->expires_at?->timestamp,
                'status' => $client['status'] ?? $attempt->status,
                'render_mode' => $client['render_mode'] ?? 'payload',
                'expires_at' => $attempt->expires_at?->toIso8601String(),
            ];
        }

        return array_merge($client, [
            'payment_attempt_id' => $attempt->id,
            'gateway' => $attempt->gateway,
            'source' => $attempt->source,
            'amount' => (float) $attempt->amount,
            'currency' => $attempt->currency,
            'payment_url' => $attempt->payment_link,
            'qr_code' => $attempt->qr_reference ?: $attempt->payment_link,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
        ]);
    }

    private function defaultDriverGateway(): string
    {
        return $this->defaultConfiguredGateway();
    }

    private function defaultCustomerGateway(): string
    {
        $this->ensureCustomerPaymentsEnabled();

        return $this->defaultConfiguredGateway();
    }

    private function ensureCustomerPaymentsEnabled(): void
    {
        $enabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        abort_unless($enabled, 422, 'Online payment is not available right now.');
    }

    private function defaultConfiguredGateway(): string
    {
        $supported = array_keys(GatewayRegistry::customerSelectablePaymentProviders());
        $configured = strtolower(trim((string) AppSetting::getValue('payment_gateway_provider', 'razorpay')));

        if (in_array($configured, $supported, true)) {
            return $configured;
        }

        $enabled = json_decode((string) AppSetting::getValue('enabled_payment_gateways', '[]'), true);
        if (is_array($enabled)) {
            foreach ($enabled as $gateway) {
                $gateway = strtolower(trim((string) $gateway));
                if (in_array($gateway, $supported, true)) {
                    return $gateway;
                }
            }
        }

        return 'razorpay';
    }
}
