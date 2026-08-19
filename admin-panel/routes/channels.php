<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Order;
use App\Models\SupportConversation;

Broadcast::channel('restaurant.{restaurantId}', function ($user, int $restaurantId) {
    return (int) $user->current_restaurant_id === $restaurantId
        || $user->restaurants()->whereKey($restaurantId)->exists();
});

Broadcast::channel('driver.{driverId}', function ($user, int $driverId) {
    return (int) $user->id === $driverId && $user->hasRole('delivery_partner');
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('order.{orderId}', function ($user, int $orderId) {
    if ($user->hasAnyRole(['super_admin', 'admin'])) {
        return true;
    }

    $order = Order::with(['restaurant.staff.user:id'])->find($orderId);
    if (! $order) {
        return false;
    }

    if ((int) $order->customer_id === (int) $user->id) {
        return true;
    }

    if ((int) $order->driver_id === (int) $user->id) {
        return true;
    }

    if ((int) optional($order->restaurant)->owner_id === (int) $user->id) {
        return true;
    }

    return (bool) optional($order->restaurant)
        ->staff
        ?->contains(fn ($staff) => (int) $staff->user_id === (int) $user->id);
});

Broadcast::channel('support.{conversationId}', function ($user, int $conversationId) {
    if ($user->hasAnyRole(['super_admin', 'admin'])) {
        return true;
    }

    $conversation = SupportConversation::with('restaurant.owner')->find($conversationId);
    if (! $conversation) {
        return false;
    }

    if ((int) $conversation->user_id === (int) $user->id) {
        return true;
    }

    return (int) optional($conversation->restaurant)->owner_id === (int) $user->id;
});

Broadcast::channel('support-queue', function ($user) {
    return $user->hasAnyRole(['super_admin', 'admin']);
});
