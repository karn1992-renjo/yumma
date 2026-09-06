<?php

namespace App\Services\Ai;

use App\Models\User;

class AiPolicyEngine
{
    private const PROTECTED_ACTION_TERMS = [
        'payout', 'refund', 'bank', 'tax', 'security', 'terminate', 'delete_user', 'role',
    ];

    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    /**
     * $approved=true means this is a human-reviewed execution reached only
     * via App\Services\Ai\AiApprovalService (called from either the admin
     * approval controller, gated on the ai.approvals.manage permission, or
     * the restaurant approval controller, gated on owning that specific
     * approval) -- both already verified the actor is authorized to act on
     * this exact item before execute() was ever called. The generic
     * ai.actions.execute permission below exists to stop a low-privilege
     * actor from triggering a *fresh, unreviewed* proposal (e.g. a
     * low-risk-auto-execute path); it would incorrectly block a restaurant
     * owner, who legitimately has no reason to hold that admin-scoped
     * permission, from approving their own restaurant's queue.
     */
    public function evaluate(array $action, ?User $actor = null, bool $approved = false): array
    {
        $isRead = (bool) ($action['read_only'] ?? false);
        $risk = (string) ($action['risk'] ?? 'medium');
        $tool = (string) ($action['tool'] ?? 'unknown');
        $params = $action['params'] ?? [];
        $mode = (string) $this->settings->get('ai_autonomy_mode', 'monitor');
        $simulation = $this->settings->bool('ai_simulation_mode', true);
        $reasons = [];

        if (! $this->settings->bool('ai_enabled')) {
            return $this->result(false, true, true, $risk, $mode, ['AI is disabled.']);
        }

        if ($this->settings->bool('ai_kill_switch')) {
            return $this->result(false, true, true, $risk, $mode, ['AI kill switch is active.']);
        }

        if ($isRead) {
            return $this->result(true, false, false, $risk, $mode, []);
        }

        // AI-authored push notifications (role_group is only set by
        // App\Services\Ai\Managers\AiNotificationManager) go out without an
        // approval step by default -- they're marketing/ops nudges bounded by
        // an hourly cadence, an audience cooldown and the offers opt-out, not
        // money-moving actions. Re-gate with ai_notifications_require_approval.
        if ($tool === 'send_notification'
            && ! empty($params['role_group'])
            && ! $this->settings->bool('ai_notifications_require_approval', false)) {
            return $this->result(true, false, false, $risk, $mode, []);
        }

        if (! $approved && $actor && method_exists($actor, 'can') && ! $actor->can('ai.actions.execute')) {
            $reasons[] = 'Current user cannot execute AI actions.';
        }

        foreach (self::PROTECTED_ACTION_TERMS as $term) {
            if (str_contains($tool, $term)) {
                $reasons[] = 'Protected financial/account/security actions require manual handling.';
            }
        }

        $amount = (float) ($params['amount'] ?? $params['maximum_budget'] ?? $params['budget'] ?? 0);
        if ($amount > (float) $this->settings->get('ai_max_action_amount', 1000)) {
            $reasons[] = 'Action exceeds configured amount guardrail.';
        }

        // set_zone_surge directly changes what customers are charged --
        // guarded separately from the generic action-amount cap above,
        // which defaults far higher than a sane per-order surge fee.
        if ($tool === 'set_zone_surge') {
            $surgeFeeAmount = (float) ($params['surge_fee_amount'] ?? 0);
            $maxSurgeFee = (float) $this->settings->get('ai_max_surge_fee_amount', 30);
            if ($surgeFeeAmount > $maxSurgeFee) {
                $reasons[] = "Surge fee of {$surgeFeeAmount} exceeds the configured maximum of {$maxSurgeFee}.";
            }
            if ($surgeFeeAmount <= 0) {
                $reasons[] = 'Surge fee must be greater than zero.';
            }
        }

        $margin = $params['expected_margin_percent'] ?? null;
        if ($margin !== null && (float) $margin < (float) $this->settings->get('ai_min_margin_percent', 8)) {
            $reasons[] = 'Expected margin is below configured guardrail.';
        }

        // Full-autonomy switch: the AI executes every non-blocked action
        // straight away -- no approval queue, no simulation. The hard rails
        // above still apply: a protected term, an over-cap amount/surge or a
        // thin margin populates $reasons and BLOCKS the action outright
        // (never queued). Toggle in Settings > AI.
        if ($this->settings->bool('ai_auto_execute', false)) {
            return $this->result($reasons === [], false, false, $risk, $mode, $reasons);
        }

        $requiresApproval = $simulation
            || $mode !== 'auto'
            || in_array($risk, ['medium', 'high', 'critical'], true)
            || ! $this->settings->bool('ai_low_risk_auto_enabled');

        return $this->result($reasons === [], $simulation, $requiresApproval || $reasons !== [], $risk, $mode, $reasons);
    }

    private function result(bool $allowed, bool $simulation, bool $requiresApproval, string $risk, string $mode, array $reasons): array
    {
        return compact('allowed', 'simulation', 'requiresApproval', 'risk', 'mode', 'reasons') + [
            'requires_approval' => $requiresApproval,
        ];
    }
}
