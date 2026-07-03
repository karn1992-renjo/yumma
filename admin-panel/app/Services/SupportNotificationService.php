<?php

namespace App\Services;

use App\Helpers\FirebaseHelper;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;

class SupportNotificationService
{
    public function __construct(
        protected ?FirebaseHelper $firebase = null,
    ) {
        $this->firebase ??= new FirebaseHelper();
    }

    public function notifyRequesterAboutAdminReply(SupportTicket $ticket, SupportTicketReply $reply): bool
    {
        $recipient = $this->resolveRequester($ticket);

        if (! $recipient) {
            return false;
        }

        $title = 'Support reply received';
        $body = sprintf(
            'Our support team replied on ticket %s.',
            $ticket->ticket_number
        );

        $payload = $this->payloadForRequester($ticket, $recipient, [
            'type' => 'support_ticket_reply',
            'reply_id' => (string) $reply->id,
        ]);

        $recipient->notify(new AppDatabaseNotification($title, $body, $payload));

        $token = $recipient->fcmTokenForApp($payload['requester_role'] ?? $this->inferRole($recipient));

        if (blank($token)) {
            return false;
        }

        return $this->firebase->sendToDevice(
            $token,
            $title,
            $body,
            $payload
        );
    }

    public function notifyRequesterAboutStatusUpdate(
        SupportTicket $ticket,
        string $oldStatus,
        string $newStatus
    ): bool {
        $recipient = $this->resolveRequester($ticket);

        if (! $recipient) {
            return false;
        }

        $title = 'Support ticket updated';
        $body = sprintf(
            'Ticket %s moved from %s to %s.',
            $ticket->ticket_number,
            str_replace('_', ' ', $oldStatus),
            str_replace('_', ' ', $newStatus)
        );

        $payload = $this->payloadForRequester($ticket, $recipient, [
            'type' => 'support_ticket_status_update',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $recipient->notify(new AppDatabaseNotification($title, $body, $payload));

        $token = $recipient->fcmTokenForApp($payload['requester_role'] ?? $this->inferRole($recipient));

        if (blank($token)) {
            return false;
        }

        return $this->firebase->sendToDevice(
            $token,
            $title,
            $body,
            $payload
        );
    }

    protected function resolveRequester(SupportTicket $ticket): ?User
    {
        $ticket->loadMissing(['user', 'restaurant.owner']);

        if ($ticket->user) {
            return $ticket->user;
        }

        return $ticket->restaurant?->owner;
    }

    protected function payloadForRequester(
        SupportTicket $ticket,
        User $recipient,
        array $extra = []
    ): array {
        return array_merge([
            'ticket_id' => (string) $ticket->id,
            'ticket_number' => (string) $ticket->ticket_number,
            'subject' => (string) $ticket->subject,
            'status' => (string) $ticket->status,
            'requester_role' => (string) ($ticket->requester_role ?: $this->inferRole($recipient)),
            'deep_link' => $this->deepLinkFor($recipient),
        ], $extra);
    }

    protected function deepLinkFor(User $recipient): string
    {
        if ($recipient->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return '/restaurant/profile/help';
        }

        return '/support';
    }

    protected function inferRole(User $recipient): string
    {
        if ($recipient->hasAnyRole(['restaurant_owner', 'restaurant_staff'])) {
            return 'restaurant';
        }

        if ($recipient->hasRole('delivery_partner')) {
            return 'driver';
        }

        return 'customer';
    }
}
