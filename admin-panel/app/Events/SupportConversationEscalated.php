<?php

namespace App\Events;

use App\Models\SupportConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportConversationEscalated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportConversation $conversation
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support-queue'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support-conversation-escalated';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->conversation->loadMissing(['user:id,name', 'order:id,order_number']);

        return [
            'type' => 'support_conversation_escalated',
            'id' => $conversation->id,
            'conversation_number' => $conversation->conversation_number,
            'requester_role' => $conversation->requester_role,
            'requester_name' => $conversation->user?->name,
            'order_number' => $conversation->order?->order_number,
            'category' => $conversation->category,
            'priority' => $conversation->priority,
        ];
    }
}
