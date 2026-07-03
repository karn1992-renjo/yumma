<?php

namespace App\Services;

use App\Events\NewOrderEvent;
use App\Helpers\FirebaseHelper;
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

        $method = strtolower((string) ($order->delivery_payment_mode ?: $order->payment_method));

        return in_array($method, ['cod', 'cash', 'cash_on_delivery'], true);
    }

    public function releaseToRestaurant(Order $order): bool
    {
        $order = $order->fresh(['restaurant.owner', 'customer']);
        if (! $order || ! $this->isReadyForRestaurant($order)) {
            return false;
        }

        broadcast(new NewOrderEvent($order, $order->restaurant_id));
        $this->notifyRestaurant($order);

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
        $payload = [
            'type' => 'NEW_ORDER',
            'event' => 'new_order',
            'role' => 'restaurant',
            'timer_duration' => '30',
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'restaurant_id' => (string) $order->restaurant_id,
            'restaurant_name' => (string) $restaurant->name,
            'pickup' => (string) $restaurant->address,
            'customer_name' => (string) ($order->customer_name ?? $order->customer?->name ?? 'Guest'),
            'customer_phone' => (string) ($order->customer_phone ?? ''),
            'delivery_address' => (string) ($order->delivery_address ?? ''),
            'amount' => (string) $order->total,
            'total' => (string) $order->total,
            'payment_status' => (string) $order->payment_status,
            'android_channel_id' => 'incoming_order_channel',
            'priority' => 'high',
            'items' => json_encode($items ?? []),
            'metadata' => json_encode([
                'pickup' => $restaurant->address,
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
