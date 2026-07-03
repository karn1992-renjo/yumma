<?php

namespace App\Events;

use App\Services\MediaStorage;

use App\Models\OrderChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OrderChatMessage $chatMessage
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('order.' . $this->chatMessage->order_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order-chat-message';
    }

    public function broadcastWith(): array
    {
        $message = $this->chatMessage->loadMissing(['sender', 'order.restaurant', 'order.driver']);

        return [
            'type' => 'order_chat_message',
            'id' => $message->id,
            'order_id' => $message->order_id,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'attachment_url' => MediaStorage::url($message->attachment_path),
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
            'meta' => $message->meta,
            'sender_id' => $message->sender_id,
            'sender_role' => $message->sender_role,
            'recipient_role' => $message->recipient_role,
            'delivered_at' => optional($message->delivered_at)->toIso8601String(),
            'read_at' => optional($message->read_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'sender_name' => $message->sender?->name,
        ];
    }
}
