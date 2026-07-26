<?php

namespace App\Events;

use App\Models\DeliveryChargeSetting;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewOrderEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $restaurantId;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, $restaurantId)
    {
        $this->order = $order;
        $this->restaurantId = $restaurantId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->restaurantId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'new-order';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $items = is_array($this->order->items) ? $this->order->items : json_decode($this->order->items, true);
        $acceptanceTimeout = DeliveryChargeSetting::getOrderAcceptanceTimeoutSeconds();

        return [
            'type' => 'NEW_ORDER',
            'role' => 'restaurant',
            'timer_duration' => $acceptanceTimeout,
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer_name ?? $this->order->customer?->name ?? 'Guest',
            'customer_phone' => $this->order->customer_phone ?? '',
            'delivery_address' => $this->order->delivery_address ?? '',
            'pickup_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
            'pickup_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
            'restaurant_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
            'restaurant_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
            'delivery_lat' => $this->order->delivery_lat !== null ? (float) $this->order->delivery_lat : null,
            'delivery_lng' => $this->order->delivery_lng !== null ? (float) $this->order->delivery_lng : null,
            'total' => (float) $this->order->total,
            'subtotal' => (float) $this->order->subtotal,
            'delivery_fee' => (float) $this->order->delivery_fee,
            'tax' => (float) $this->order->tax,
            'discount' => (float) $this->order->discount,
            'status' => $this->order->status,
            'items' => $items ?? [],
            'metadata' => [
                'pickup' => $this->order->restaurant?->address,
                'pickup_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
                'pickup_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
                'delivery_lat' => $this->order->delivery_lat !== null ? (float) $this->order->delivery_lat : null,
                'delivery_lng' => $this->order->delivery_lng !== null ? (float) $this->order->delivery_lng : null,
                'items' => $items ?? [],
                'amount' => (float) $this->order->total,
            ],
            'items_count' => count($items ?? []),
            'payment_method' => $this->order->payment_method,
            'payment_status' => $this->order->payment_status,
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}
