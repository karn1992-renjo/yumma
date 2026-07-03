<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatTypingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public int $senderId,
        public string $senderRole,
        public ?string $recipientRole,
        public bool $isTyping
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
        return 'order-chat-typing';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'order_chat_typing',
            'order_id' => $this->orderId,
            'sender_id' => $this->senderId,
            'sender_role' => $this->senderRole,
            'recipient_role' => $this->recipientRole,
            'is_typing' => $this->isTyping,
        ];
    }
}
