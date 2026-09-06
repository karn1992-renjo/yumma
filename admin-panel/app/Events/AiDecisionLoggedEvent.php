<?php

namespace App\Events;

use App\Models\AiDecision;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiDecisionLoggedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AiDecision $decision)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.ai-activity')];
    }

    public function broadcastAs(): string
    {
        return 'ai-decision-logged';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'AI_DECISION_LOGGED',
            'data' => [
                'id' => $this->decision->id,
                'agent_key' => $this->decision->agent_key,
                'decision_type' => $this->decision->decision_type,
                'risk_level' => $this->decision->risk_level,
                'policy_status' => $this->decision->policy_status,
                'execution_status' => $this->decision->execution_status,
                'requires_approval' => (bool) $this->decision->requires_approval,
                'auto_executed' => (bool) $this->decision->auto_executed,
                'reason_summary' => $this->decision->reason_summary,
                'created_at' => $this->decision->created_at?->toIso8601String(),
            ],
            'broadcasted_at' => now()->toIso8601String(),
        ];
    }
}
