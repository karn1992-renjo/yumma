<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Always dispatched via notifyNow() (see OrderStatusPushService), never
 * notify() -- so this deliberately does NOT implement ShouldQueue. Declaring
 * queueability here would be misleading: notifyNow() sends synchronously
 * regardless, and this app has no confirmed queue worker running, so a real
 * ShouldQueue notification would silently never deliver if a future call
 * site switched to notify().
 */
class OrderStatusNotification extends Notification
{
    protected $order;
    protected $message;
    protected string $role;
    
    public function __construct($order, $message, string $role = 'customer')
    {
        $this->order = $order;
        $this->message = $message;
        $this->role = $role;
    }
    
    public function via($notifiable)
    {
        // Order status is broadcast separately by OrderStatusUpdatedEvent.
        // This notification is the durable record shown in the app feed.
        return ['database'];
    }
    
    public function toArray($notifiable)
    {
        $statusLabel = ucwords(str_replace('_', ' ', (string) $this->order->status));

        return [
            'type' => 'order_status_updated',
            'role' => $this->role,
            'title' => 'Order ' . $statusLabel,
            'body' => $this->message,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => $this->message,
            'deep_link' => match ($this->role) {
                'restaurant' => '/restaurant/order',
                'driver' => '/driver/order',
                default => '/order/track',
            },
        ];
    }
}
