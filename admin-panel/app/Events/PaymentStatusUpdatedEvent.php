<?php

namespace App\Events;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event,
        public ?PaymentAttempt $attempt = null,
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('order.' . $this->order->id),
            new PrivateChannel('user.' . $this->order->customer_id),
        ];

        if ($this->order->driver_id) {
            $channels[] = new PrivateChannel('driver.' . $this->order->driver_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'payment_status' => $this->order->payment_status,
            'payment_method' => $this->order->payment_method,
            'payment_source' => $this->order->payment_source,
            'payment_gateway' => $this->order->payment_gateway,
            'transaction_id' => $this->order->payment_id,
            'paid_at' => $this->order->paid_at?->toIso8601String(),
            'attempt' => $this->attempt ? [
                'id' => $this->attempt->id,
                'gateway' => $this->attempt->gateway,
                'source' => $this->attempt->source,
                'status' => $this->attempt->status,
                'amount' => (float) $this->attempt->amount,
                'payment_link' => $this->attempt->payment_link,
                'qr_reference' => $this->attempt->qr_reference,
                'expires_at' => $this->attempt->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
