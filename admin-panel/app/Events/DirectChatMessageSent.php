<?php

namespace App\Events;

use App\Models\DirectChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DirectChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return $this->message->conversation
            ->participants()
            ->pluck('users.id')
            ->map(fn ($userId) => new PrivateChannel('user.' . $userId))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'direct-chat-message';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('sender');

        return [
            'type' => 'direct_chat_message',
            'conversation_id' => $message->conversation_id,
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name,
                'message' => $message->message,
                'message_type' => $message->message_type,
                'meta' => $message->meta,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ],
        ];
    }
}
