<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatReadReceiptUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $messageIds
     */
    public function __construct(
        public int $orderId,
        public string $readerRole,
        public int $readerId,
        public array $messageIds,
        public string $readAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->orderId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-chat-read';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'order_chat_read',
            'order_id' => $this->orderId,
            'reader_role' => $this->readerRole,
            'reader_id' => $this->readerId,
            'message_ids' => $this->messageIds,
            'read_at' => $this->readAt,
        ];
    }
}
