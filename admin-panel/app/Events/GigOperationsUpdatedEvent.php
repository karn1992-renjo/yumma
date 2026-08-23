<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GigOperationsUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $snapshot)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.gig-operations')];
    }

    public function broadcastAs(): string
    {
        return 'gig-operations-updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'GIG_OPERATIONS_UPDATED',
            'data' => $this->snapshot,
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }
}