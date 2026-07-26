<?php

namespace App\Events;

use App\Models\DeliveryChargeSetting;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverOrderAssignedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public int $driverId
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('driver.' . $this->driverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'driver-order-assigned';
    }

    public function broadcastWith(): array
    {
        $acceptanceTimeout = DeliveryChargeSetting::getOrderAcceptanceTimeoutSeconds();

        return [
            'type' => 'NEW_ORDER',
            'event' => 'driver_order_assigned',
            'role' => 'driver',
            'timer_duration' => $acceptanceTimeout,
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'driver_id' => $this->driverId,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'restaurant_name' => $this->order->restaurant?->name,
            'restaurant_address' => $this->order->restaurant?->address,
            'pickup_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
            'pickup_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
            'restaurant_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
            'restaurant_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
            'customer_name' => $this->order->customer_name,
            'delivery_address' => $this->order->delivery_address,
            'delivery_lat' => $this->order->delivery_lat !== null ? (float) $this->order->delivery_lat : null,
            'delivery_lng' => $this->order->delivery_lng !== null ? (float) $this->order->delivery_lng : null,
            'delivery_fee' => (float) $this->order->delivery_fee,
            'earnings' => (float) ($this->order->driver_earning ?? $this->order->delivery_fee ?? 0),
            'total' => (float) $this->order->total,
            'metadata' => [
                'pickup' => $this->order->restaurant?->address,
                'pickup_lat' => $this->order->restaurant?->latitude !== null ? (float) $this->order->restaurant->latitude : null,
                'pickup_lng' => $this->order->restaurant?->longitude !== null ? (float) $this->order->restaurant->longitude : null,
                'delivery_lat' => $this->order->delivery_lat !== null ? (float) $this->order->delivery_lat : null,
                'delivery_lng' => $this->order->delivery_lng !== null ? (float) $this->order->delivery_lng : null,
                'amount' => (float) $this->order->total,
                'earnings' => (float) ($this->order->driver_earning ?? $this->order->delivery_fee ?? 0),
            ],
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}
