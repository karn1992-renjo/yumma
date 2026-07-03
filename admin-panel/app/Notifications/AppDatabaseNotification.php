<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppDatabaseNotification extends Notification
{
    public function __construct(
        protected string $title,
        protected string $body,
        protected array $data = []
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return array_merge($this->data, [
            'title' => $this->title,
            'body' => $this->body,
            'message' => $this->body,
        ]);
    }
}
