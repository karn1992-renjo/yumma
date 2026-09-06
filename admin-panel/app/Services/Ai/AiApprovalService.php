<?php

namespace App\Services\Ai;

use App\Models\AiApproval;
use App\Models\User;

/**
 * The single "approve/reject a pending AI action" execution path -- used by
 * both App\Http\Controllers\Admin\AiControlCenterController (admin queue)
 * and App\Http\Controllers\Restaurant\AiApprovalController (restaurant
 * queue, for restaurant-funded promotions and menu price suggestions), so
 * both reviewers share the exact same, already-tested execute-for-real
 * logic instead of duplicating it.
 */
class AiApprovalService
{
    public function __construct(
        private readonly AiToolRegistry $tools,
        private readonly AiActivityBroadcastService $activity,
    ) {
    }

    public function approve(AiApproval $approval, ?User $reviewer, ?string $note = null): array
    {
        $approval->load(['decision', 'action']);
        $action = $approval->action;

        if ($action) {
            $this->tools->execute($action->action_key, $action->parameters ?? [], $reviewer, $approval->decision, approved: true);
        }

        $approval->forceFill([
            'status' => 'approved',
            'admin_note' => $note,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ])->save();

        if ($approval->decision) {
            $this->activity->broadcast($approval->decision->fresh());
        }

        return [
            'success' => true,
            'status' => 'approved',
            'action' => $approval->action?->fresh()->only(['status', 'result']),
        ];
    }

    public function reject(AiApproval $approval, ?User $reviewer, ?string $note = null): array
    {
        $approval->forceFill([
            'status' => 'rejected',
            'admin_note' => $note,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
        ])->save();

        $approval->action?->forceFill(['status' => 'rejected'])->save();

        if ($approval->decision) {
            $this->activity->broadcast($approval->decision->fresh());
        }

        return ['success' => true, 'status' => 'rejected'];
    }
}
