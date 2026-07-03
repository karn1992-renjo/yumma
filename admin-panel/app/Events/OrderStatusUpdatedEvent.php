<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $restaurantId;

    public function __construct(Order $order, $restaurantId)
    {
        $this->order = $order;
        $this->restaurantId = $restaurantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.' . $this->restaurantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-status-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->order->status)),
            'driver_id' => $this->order->driver_id,
        ];
    }
}
