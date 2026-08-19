<?php

namespace App\Events;

use App\Models\SupportMessage;
use App\Services\MediaStorage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportMessage $message
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support-message';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('sender');

        return [
            'type' => 'support_message',
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender?->name,
            'message_type' => $message->message_type,
            'message' => $message->message,
            'attachment_url' => MediaStorage::url($message->attachment_path),
            'attachment_name' => $message->attachment_name,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
            'meta' => $message->meta,
            'delivered_at' => optional($message->delivered_at)->toIso8601String(),
            'read_at' => optional($message->read_at)->toIso8601String(),
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }
}
