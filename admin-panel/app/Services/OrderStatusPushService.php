<?php

namespace App\Services;

use App\Events\CustomerOrderStatusUpdatedEvent;
use App\Helpers\FirebaseHelper;
use App\Models\Order;
use App\Models\RestaurantStaff;
use App\Notifications\OrderStatusNotification;

class OrderStatusPushService
{
    public function __construct(
        protected ?FirebaseHelper $firebase = null,
    ) {
        $this->firebase ??= new FirebaseHelper();
    }

    public function notifyCustomer(Order $order, ?string $message = null): bool
    {
        $order->loadMissing(['customer', 'restaurant']);

        $customer = $order->customer;
        if (! $customer) {
            return false;
        }

        $statusLabel = str_replace('_', ' ', (string) $order->status);
        $title = 'Order ' . ucwords($statusLabel);
        $message ??= $this->messageFor($order);
        // Store the notification immediately so the customer notification feed
        // does not depend on a queue worker being available.
        $customer->notifyNow(new OrderStatusNotification($order, $message, 'customer'));

        try {
            broadcast(new CustomerOrderStatusUpdatedEvent($order));
        } catch (\Throwable $exception) {
            \Log::warning('Customer order status broadcast failed.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }

        $token = $customer->fcmTokenForApp('customer');

        if (blank($token)) {
            return false;
        }

        return $this->firebase->sendToDevice(
            $token,
            $title,
            $message,
            [
                'type' => 'customer_order_status',
                'role' => 'customer',
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'status' => (string) $order->status,
                'restaurant_name' => (string) optional($order->restaurant)->name,
                'notification_title' => $title,
                'notification_body' => $message,
                'android_channel_id' => 'order_status_channel_custom',
                'deep_link' => '/order/track',
            ],
        );
    }

    public function notifyCustomerStatusChanged(Order $order, ?string $message = null): bool
    {
        $results = $this->notifyParticipants($order, $message);

        return (bool) ($results['customer'] ?? false);
    }

    public function notifyParticipants(Order $order, ?string $customerMessage = null, array $roles = ['customer', 'restaurant', 'driver']): array
    {
        $order->loadMissing(['customer', 'restaurant.owner', 'driver']);
        $roles = collect($roles)->map(fn ($role) => strtolower((string) $role))->all();

        return [
            'customer' => in_array('customer', $roles, true) ? $this->notifyCustomer($order, $customerMessage) : false,
            'restaurant' => in_array('restaurant', $roles, true) ? $this->notifyRestaurant($order) : [
                'success' => 0,
                'failure' => 0,
                'failure_reason' => null,
            ],
            'driver' => in_array('driver', $roles, true) ? $this->notifyDriver($order) : false,
        ];
    }

    public function notifyRestaurant(Order $order, ?string $message = null): array
    {
        $order->loadMissing(['restaurant.owner']);

        $tokens = $this->restaurantTokens($order);
        $orderNumber = (string) ($order->order_number ?: $order->id);
        $statusLabel = ucwords(str_replace('_', ' ', (string) $order->status));
        $title = "Order #{$orderNumber} {$statusLabel}";
        $message ??= "Order #{$orderNumber} status changed to {$statusLabel}.";
        $this->restaurantUsers($order)
            ->each(fn ($user) => $user->notifyNow(new OrderStatusNotification($order, $message, 'restaurant')));

        return $this->firebase->sendToDevices(
            $tokens,
            $title,
            $message,
            $this->statusPayload($order, 'restaurant', $title, $message, '/restaurant/order'),
        );
    }

    public function notifyDriver(Order $order, ?string $message = null): bool
    {
        $order->loadMissing('driver');

        $driver = $order->driver;
        if (! $driver) {
            return false;
        }

        $orderNumber = (string) ($order->order_number ?: $order->id);
        $statusLabel = ucwords(str_replace('_', ' ', (string) $order->status));
        $title = "Order #{$orderNumber} {$statusLabel}";
        $message ??= "Order #{$orderNumber} status changed to {$statusLabel}.";
        $driver->notifyNow(new OrderStatusNotification($order, $message, 'driver'));

        $token = $driver->fcmTokenForApp('driver');

        if (blank($token)) {
            return false;
        }

        return $this->firebase->sendToDevice(
            $token,
            $title,
            $message,
            $this->statusPayload($order, 'driver', $title, $message, '/driver/order'),
        );
    }

    public function messageFor(Order $order): string
    {
        $order->loadMissing('restaurant');

        $orderNumber = (string) ($order->order_number ?: $order->id);
        $restaurantName = (string) optional($order->restaurant)->name;
        $restaurantSuffix = $restaurantName !== '' ? " by {$restaurantName}" : '';

        return match ((string) $order->status) {
            'pending' => "Your order #{$orderNumber} has been placed.",
            'confirmed' => "Your order #{$orderNumber} has been confirmed{$restaurantSuffix}.",
            'preparing' => "Your order #{$orderNumber} is now being prepared.",
            'ready_for_pickup' => ($order->order_type ?? 'delivery') === 'takeaway'
                ? "Your takeaway order #{$orderNumber} is ready to collect."
                : "Your order #{$orderNumber} is ready for pickup.",
            'reached_pickup' => "Your order #{$orderNumber} driver has reached the restaurant.",
            'picked_up' => "Your order #{$orderNumber} has been picked up.",
            'on_the_way' => "Your order #{$orderNumber} is on the way.",
            'delivered' => "Your order #{$orderNumber} has been delivered.",
            'cancelled' => "Your order #{$orderNumber} has been cancelled.",
            default => "Your order #{$orderNumber} status changed to {$order->status}.",
        };
    }

    private function statusPayload(Order $order, string $role, string $title, string $message, string $deepLink): array
    {
        return [
            'type' => $role === 'customer' ? 'customer_order_status' : 'order_status_updated',
            'role' => $role,
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'restaurant_id' => (string) $order->restaurant_id,
            'driver_id' => (string) ($order->driver_id ?? ''),
            'restaurant_name' => (string) optional($order->restaurant)->name,
            'notification_title' => $title,
            'notification_body' => $message,
            'android_channel_id' => $role === 'customer'
                ? 'order_status_channel_custom'
                : 'default_notification_channel_custom',
            'deep_link' => $deepLink,
        ];
    }

    private function restaurantTokens(Order $order): array
    {
        return $this->restaurantUsers($order)
            ->map(fn ($user) => $user->fcmTokenForApp('restaurant'))
            ->filter(fn ($token) => filled($token))
            ->unique()
            ->values()
            ->all();
    }

    private function restaurantUsers(Order $order)
    {
        $restaurant = $order->restaurant;
        if (! $restaurant) {
            return collect();
        }

        $staffUsers = RestaurantStaff::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with('user:id,fcm_token,restaurant_fcm_token')
            ->get()
            ->pluck('user')
            ->filter();

        return collect([$restaurant->owner])
            ->filter()
            ->merge($staffUsers)
            ->unique('id')
            ->values();
    }
}
