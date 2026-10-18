<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\Log;

/**
 * Runs on a schedule (routes/console.php). Flags Support Desk tickets that
 * have gone quiet past their priority's threshold -- urgent tickets get the
 * shortest leash, since those are the ones an SLA actually exists for.
 * Mirrors RestaurantApprovalReminderService's run()+last_reminded_at
 * idempotency pattern exactly, so a ticket is never re-nagged more than
 * once per threshold window.
 */
class SupportSlaReminderService
{
    /** Hours of silence (since last_message_at) before a reminder fires, by priority. */
    private const THRESHOLDS = [
        'urgent' => 2,
        'high' => 6,
        'medium' => 24,
        'normal' => 24,
        'low' => 48,
    ];

    public function run(): void
    {
        $overdue = SupportConversation::where('stage', 'human')
            ->whereIn('status', ['open', 'in_progress'])
            ->get()
            ->filter(fn (SupportConversation $conversation) => $this->isOverdue($conversation));

        foreach ($overdue as $conversation) {
            $this->remind($conversation);
        }
    }

    private function isOverdue(SupportConversation $conversation): bool
    {
        $thresholdHours = self::THRESHOLDS[$conversation->priority] ?? self::THRESHOLDS['medium'];
        $staleSince = $conversation->last_message_at ?? $conversation->created_at;

        if (! $staleSince || $staleSince->gt(now()->subHours($thresholdHours))) {
            return false;
        }

        // Re-remind at most once per threshold window, not every run.
        return ! $conversation->last_reminded_at
            || $conversation->last_reminded_at->lt(now()->subHours($thresholdHours));
    }

    private function remind(SupportConversation $conversation): void
    {
        $recipientIds = $conversation->assigned_to
            ? [$conversation->assigned_to]
            : User::role(['super_admin', 'admin', 'support'])->pluck('id')->all();

        $title = $conversation->priority === 'urgent' ? '🚨 Urgent ticket needs attention' : '⏰ Ticket needs a reply';
        $requesterLabel = ucfirst($conversation->requester_role) . ' · ' . $conversation->conversation_number;
        $hoursQuiet = $conversation->last_message_at ? $conversation->last_message_at->diffInHours(now()) : null;
        $message = $hoursQuiet !== null
            ? "{$requesterLabel} has had no reply for {$hoursQuiet}h."
            : "{$requesterLabel} is still waiting on a first reply.";

        foreach (User::whereIn('id', $recipientIds)->get() as $recipient) {
            try {
                $recipient->notify(new AppDatabaseNotification($title, $message, [
                    'type' => 'support_sla_reminder',
                    'deep_link' => '/support/' . $conversation->id,
                    'conversation_id' => $conversation->id,
                    'conversation_number' => $conversation->conversation_number,
                ]));
            } catch (\Throwable $e) {
                Log::warning('Support SLA reminder notification failed: ' . $e->getMessage());
            }
        }

        $conversation->update(['last_reminded_at' => now()]);
    }
}
