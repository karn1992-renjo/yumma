<?php

namespace App\Services\Petpooja;

use App\Models\Order;
use App\Models\PetpoojaOrderLog;
use Illuminate\Support\Facades\Log;

/**
 * Pushes our own driver's delivery status to Petpooja (the Rider Info
 * webhook -- despite the name, this is an outbound call we make) so
 * their dashboard reflects live status without polling. Built entirely
 * from Order's existing driver columns -- no new model needed.
 */
class PetpoojaRiderPushService
{
    public function __construct(private readonly PetpoojaApiClient $client)
    {
    }

    public function push(Order $order): bool
    {
        $config = $order->restaurant?->petpoojaConfig;
        if (! $config || $order->pos_provider !== 'petpooja' || ! $config->hasCredentials() || ! $order->driver_id) {
            return false;
        }

        $status = $this->statusFor($order);
        if ($status === null) {
            return false;
        }

        $driver = $order->driver;

        try {
            $response = $this->client->pushRiderStatus(
                $config,
                (string) $order->id,
                $status,
                $driver?->name ?? '',
                $driver?->phone ?? ''
            );
            $body = $response->json() ?? [];
            $success = $response->successful() && (string) ($body['success'] ?? '') === 'success';

            PetpoojaOrderLog::create([
                'order_id' => $order->id,
                'direction' => 'rider_status',
                'request_payload' => ['status' => $status],
                'response_payload' => $body,
                'http_status' => $response->status(),
                'success' => $success,
                'message' => $body['message'] ?? null,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::warning('Petpooja rider status push failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function statusFor(Order $order): ?string
    {
        return match ($order->status) {
            'reached_pickup' => 'rider-arrived',
            'picked_up' => 'pickedup',
            'on_the_way' => 'pickedup',
            'delivered' => 'delivered',
            default => $order->driver_id ? 'rider-assigned' : null,
        };
    }
}
