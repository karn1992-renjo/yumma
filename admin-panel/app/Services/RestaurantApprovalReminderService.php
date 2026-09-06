<?php

namespace App\Services;

use App\Models\AiApproval;
use App\Models\Restaurant;
use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Log;

/**
 * Runs daily (routes/console.php). Nags a restaurant owner with one bundled
 * push per day for every AI proposal still sitting in their approval queue
 * (App\Http\Controllers\Restaurant\AiApprovalController), stamping
 * last_reminded_at so it never sends twice in the same day. Stops the
 * moment a restaurant acts (approve/reject flips status away from
 * 'pending', which removes it from this query entirely).
 */
class RestaurantApprovalReminderService
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly PushNotificationService $pushNotifications,
    ) {
    }

    public function run(): void
    {
        if (! $this->settings->bool('ai_enabled') || $this->settings->bool('ai_kill_switch')) {
            return;
        }

        $pending = AiApproval::where('approver_type', 'restaurant')
            ->where('status', 'pending')
            ->whereNotNull('restaurant_id')
            ->where(function ($query) {
                $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', now()->startOfDay());
            })
            ->get()
            ->groupBy('restaurant_id');

        foreach ($pending as $restaurantId => $approvals) {
            $this->remind((int) $restaurantId, $approvals->pluck('id')->all());
        }
    }

    private function remind(int $restaurantId, array $approvalIds): void
    {
        $restaurant = Restaurant::find($restaurantId);
        $owner = $restaurant?->owner;

        if (! $owner) {
            return;
        }

        $count = count($approvalIds);
        $title = '📋 AI proposals waiting for you';
        $message = $count === 1
            ? 'You have 1 AI proposal waiting for your approval on the dashboard.'
            : "You have {$count} AI proposals waiting for your approval on the dashboard.";

        try {
            $this->pushNotifications->sendToUser($owner, $title, $message, ['type' => 'ai_approval_reminder'], 'restaurant');
        } catch (\Throwable $exception) {
            Log::warning('Restaurant AI approval reminder push failed.', ['restaurant_id' => $restaurantId, 'error' => $exception->getMessage()]);
        }

        AiApproval::whereIn('id', $approvalIds)->update(['last_reminded_at' => now()]);
    }
}
