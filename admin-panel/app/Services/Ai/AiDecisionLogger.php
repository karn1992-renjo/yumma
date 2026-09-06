<?php

namespace App\Services\Ai;

use App\Models\AiAction;
use App\Models\AiApproval;
use App\Models\AiDecision;
use App\Models\User;

class AiDecisionLogger
{
    private const VALID_RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

    public function __construct(private readonly AiActivityBroadcastService $activity)
    {
    }

    /**
     * $data['confidence'] / $data['expected_financial_impact'] and
     * $data['risk_level'] often originate from a decoded LLM JSON response
     * (App\Services\Ai\AiOrchestrator::normalizeProposal()/chat()) that is
     * NOT schema-enforced -- providers occasionally return e.g.
     * confidence: "high" instead of a 0-1 number. confidence/
     * expected_financial_impact are decimal DB columns, so an unsanitized
     * non-numeric string throws a PDOException and aborts the whole
     * decision write. Sanitize every field here, the single place every
     * decision (scheduled run + chat) gets persisted.
     */
    public function decision(array $data, ?User $actor = null): AiDecision
    {
        $decision = AiDecision::create([
            'agent_key' => $data['agent_key'] ?? 'system',
            'decision_type' => $data['decision_type'] ?? 'analysis',
            'trigger' => $data['trigger'] ?? null,
            'provider' => $data['provider'] ?? null,
            'model' => $data['model'] ?? null,
            'risk_level' => $this->sanitizeRiskLevel($data['risk_level'] ?? null),
            'confidence' => $this->sanitizeDecimal($data['confidence'] ?? null, 0, 1),
            'input_snapshot' => $data['input_snapshot'] ?? [],
            'reason_summary' => $data['reason_summary'] ?? $data['summary'] ?? null,
            'proposed_action' => $data['proposed_action'] ?? $data['recommendation'] ?? [],
            'expected_result' => $data['expected_result'] ?? [],
            'expected_financial_impact' => $this->sanitizeDecimal($data['expected_financial_impact'] ?? null),
            'policy_status' => $data['policy_status'] ?? 'pending',
            'requires_approval' => (bool) ($data['requires_approval'] ?? true),
            'auto_executed' => (bool) ($data['auto_executed'] ?? false),
            'execution_status' => $data['execution_status'] ?? 'pending',
            'execution_result' => $data['execution_result'] ?? null,
        ]);

        $this->activity->broadcast($decision);

        return $decision;
    }

    private function sanitizeDecimal(mixed $value, ?float $min = null, ?float $max = null): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if ($min !== null) {
            $float = max($min, $float);
        }
        if ($max !== null) {
            $float = min($max, $float);
        }

        return $float;
    }

    private function sanitizeRiskLevel(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($value, self::VALID_RISK_LEVELS, true) ? $value : 'medium';
    }

    public function action(AiDecision $decision, string $tool, array $params, array $outcome, string $status): AiAction
    {
        return AiAction::create([
            'ai_decision_id' => $decision->id,
            'action_key' => $tool,
            'risk_level' => $outcome['policy']['risk'] ?? 'medium',
            'parameters' => $params,
            'policy_result' => $outcome['policy'] ?? [],
            'result' => $outcome,
            'status' => $status,
            'executed_by' => auth()->id(),
            'executed_at' => in_array($status, ['executed', 'simulated'], true) ? now() : null,
        ]);
    }

    public function approval(AiDecision $decision, ?AiAction $action = null, string $status = 'pending', string $approverType = 'admin', ?int $restaurantId = null): AiApproval
    {
        return AiApproval::create([
            'ai_decision_id' => $decision->id,
            'ai_action_id' => $action?->id,
            'status' => $status,
            'approver_type' => $approverType,
            'restaurant_id' => $restaurantId,
            'requested_payload' => $action?->parameters ?? $decision->proposed_action,
        ]);
    }
}
