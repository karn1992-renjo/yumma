<?php

namespace App\Services;

use App\Events\NewOrderEvent;
use App\Helpers\FirebaseHelper;
use App\Models\DeliveryChargeSetting;
use App\Models\Order;
use App\Models\RestaurantStaff;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\Log;

class OrderReleaseService
{
    public function __construct(
        private readonly FirebaseHelper $firebase,
        private readonly PrinterService $printerService
    ) {
    }

    public function isReadyForRestaurant(Order $order): bool
    {
        if ($order->payment_status === 'success') {
            return true;
        }

        return $order->isCashOnDelivery();
    }

    public function releaseToRestaurant(Order $order): bool
    {
        $order = $order->fresh(['restaurant.owner', 'customer']);
        if (! $order || ! $this->isReadyForRestaurant($order)) {
            return false;
        }

        try {
            broadcast(new NewOrderEvent($order, $order->restaurant_id));
        } catch (\Throwable $e) {
            Log::warning('New order broadcast failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $this->notifyRestaurant($order);
        } catch (\Throwable $e) {
            Log::warning('Restaurant new order notification failed.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $this->printerService->autoPrintNewOrder($order);
        } catch (\Throwable $e) {
            Log::error('Restaurant auto-print failed: ' . $e->getMessage());
        }

        return true;
    }

    private function notifyRestaurant(Order $order): void
    {
        $restaurant = $order->restaurant;
        if (! $restaurant) {
            return;
        }

        $title = 'New order received';
        $body = "Order #{$order->order_number} is ready for restaurant confirmation.";
        $items = is_array($order->items) ? $order->items : json_decode((string) $order->items, true);
        $acceptanceTimeout = DeliveryChargeSetting::getOrderAcceptanceTimeoutSeconds();
        $payload = [
            'type' => 'NEW_ORDER',
            'event' => 'new_order',
            'role' => 'restaurant',
            'timer_duration' => (string) $acceptanceTimeout,
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'restaurant_id' => (string) $order->restaurant_id,
            'restaurant_name' => (string) $restaurant->name,
            'pickup' => (string) $restaurant->address,
            'customer_name' => (string) ($order->customer_name ?? $order->customer?->name ?? 'Guest'),
            'customer_phone' => (string) ($order->customer_phone ?? ''),
            'delivery_address' => (string) ($order->delivery_address ?? ''),
            'pickup_lat' => (string) ($restaurant->latitude ?? ''),
            'pickup_lng' => (string) ($restaurant->longitude ?? ''),
            'restaurant_lat' => (string) ($restaurant->latitude ?? ''),
            'restaurant_lng' => (string) ($restaurant->longitude ?? ''),
            'delivery_lat' => (string) ($order->delivery_lat ?? ''),
            'delivery_lng' => (string) ($order->delivery_lng ?? ''),
            'amount' => (string) $order->total,
            'total' => (string) $order->total,
            'payment_status' => (string) $order->payment_status,
            'android_channel_id' => 'incoming_order_channel',
            'priority' => 'high',
            'items' => json_encode($items ?? []),
            'metadata' => json_encode([
                'pickup' => $restaurant->address,
                'pickup_lat' => $restaurant->latitude !== null ? (float) $restaurant->latitude : null,
                'pickup_lng' => $restaurant->longitude !== null ? (float) $restaurant->longitude : null,
                'delivery_lat' => $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
                'delivery_lng' => $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
                'items' => $items ?? [],
                'amount' => (float) $order->total,
            ]),
        ];

        $recipients = collect([$restaurant->owner])
            ->filter()
            ->merge(
                RestaurantStaff::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true)
                    ->with('user:id,fcm_token,restaurant_fcm_token')
                    ->get()
                    ->pluck('user')
                    ->filter()
            )
            ->unique('id')
            ->values();

        foreach ($recipients as $recipient) {
            $recipient->notify(new AppDatabaseNotification($title, $body, $payload));
        }

        $this->firebase->sendToDevices(
            $recipients
                ->map(fn ($user) => $user->fcmTokenForApp('restaurant'))
                ->filter(fn ($token) => filled($token))
                ->unique()
                ->values()
                ->all(),
            $title,
            $body,
            $payload
        );
    }
}
