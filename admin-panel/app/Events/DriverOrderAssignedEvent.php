<?php

namespace App\Events;

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
        return [
            'type' => 'NEW_ORDER',
            'event' => 'driver_order_assigned',
            'role' => 'driver',
            'timer_duration' => 30,
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'driver_id' => $this->driverId,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'restaurant_name' => $this->order->restaurant?->name,
            'restaurant_address' => $this->order->restaurant?->address,
            'customer_name' => $this->order->customer_name,
            'delivery_address' => $this->order->delivery_address,
            'delivery_fee' => (float) $this->order->delivery_fee,
            'earnings' => (float) ($this->order->driver_earning ?? $this->order->delivery_fee ?? 0),
            'total' => (float) $this->order->total,
            'metadata' => [
                'pickup' => $this->order->restaurant?->address,
                'amount' => (float) $this->order->total,
                'earnings' => (float) ($this->order->driver_earning ?? $this->order->delivery_fee ?? 0),
            ],
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}
