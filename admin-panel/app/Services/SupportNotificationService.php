<?php

namespace App\Services;

use App\Helpers\FirebaseHelper;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;

class SupportNotificationService
{
    public function __construct(
        protected ?FirebaseHelper $firebase = null,
    ) {
        $this->firebase ??= new FirebaseHelper();
    }

    public function notifyRequesterAboutAdminReply(SupportConversation $conversation, SupportMessage $message): bool
    {
        $recipient = $conversation->user;

        if (! $recipient) {
            return false;
        }

        $title = 'Support reply received';
        $body = 'Our support team replied on conversation ' . $conversation->conversation_number . '.';

        return $this->notify($conversation, $recipient, $title, $body, [
            'type' => 'support_message',
            'message_id' => (string) $message->id,
        ]);
    }

    public function notifyRequesterAboutStatusUpdate(
        SupportConversation $conversation,
        string $oldStatus,
        string $newStatus
    ): bool {
        $recipient = $conversation->user;

        if (! $recipient) {
            return false;
        }

        $title = 'Support conversation updated';
        $body = sprintf(
            'Conversation %s moved from %s to %s.',
            $conversation->conversation_number,
            str_replace('_', ' ', $oldStatus),
            str_replace('_', ' ', $newStatus)
        );

        return $this->notify($conversation, $recipient, $title, $body, [
            'type' => 'support_conversation_status_update',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    protected function notify(SupportConversation $conversation, User $recipient, string $title, string $body, array $extra): bool
    {
        $payload = array_merge([
            'conversation_id' => (string) $conversation->id,
            'conversation_number' => (string) $conversation->conversation_number,
            'stage' => (string) $conversation->stage,
            'status' => (string) $conversation->status,
            'requester_role' => (string) $conversation->requester_role,
            'deep_link' => $this->deepLinkFor($conversation->requester_role),
        ], $extra);

        $recipient->notify(new AppDatabaseNotification($title, $body, $payload));

        $token = $recipient->fcmTokenForApp($conversation->requester_role);

        if (blank($token)) {
            return false;
        }

        return $this->firebase->sendToDevice($token, $title, $body, $payload);
    }

    protected function deepLinkFor(string $requesterRole): string
    {
        return match ($requesterRole) {
            'restaurant' => '/restaurant/profile/help',
            'driver' => '/support',
            default => '/support',
        };
    }
}
