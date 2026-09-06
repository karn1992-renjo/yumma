<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AiApproval;
use App\Services\Ai\AiApprovalService;
use Illuminate\Http\Request;

class AiApprovalController extends Controller
{
    use ResolvesRestaurantContext;

    public function approve(Request $request, AiApproval $approval, AiApprovalService $approvals)
    {
        $this->authorizeRestaurant($approval);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $result = $approvals->approve($approval, $request->user(), $validated['note'] ?? null);

        return response()->json($result + ['approval_id' => $approval->id]);
    }

    public function reject(Request $request, AiApproval $approval, AiApprovalService $approvals)
    {
        $this->authorizeRestaurant($approval);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $result = $approvals->reject($approval, $request->user(), $validated['note'] ?? null);

        return response()->json($result + ['approval_id' => $approval->id]);
    }

    private function authorizeRestaurant(AiApproval $approval): void
    {
        $restaurant = $this->currentRestaurant();

        abort_if(
            $approval->approver_type !== 'restaurant' || ! $restaurant || $approval->restaurant_id !== $restaurant->id,
            403
        );
    }
}
