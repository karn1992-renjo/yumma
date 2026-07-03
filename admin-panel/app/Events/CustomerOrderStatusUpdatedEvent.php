<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->order->customer_id)];
    }

    public function broadcastAs(): string
    {
        return 'order-status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->order->status)),
            'restaurant_id' => $this->order->restaurant_id,
            'driver_id' => $this->order->driver_id,
        ];
    }
}
