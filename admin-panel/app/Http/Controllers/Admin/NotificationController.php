<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportConversation;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the notification center dropdown.
     */
    public function recent(Request $request)
    {
        $user = $request->user();
        $limit = (int) $request->input('limit', 10);

        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                return $this->formatNotification($notification);
            });

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Get notification statistics for the dashboard.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();

        // Pending orders count
        $pendingOrders = Order::whereIn('status', ['pending', 'confirmed'])->count();

        // Open support conversations
        $openTickets = SupportConversation::whereIn('status', ['open', 'in_progress'])->count();

        return response()->json([
            'success' => true,
            'unread_notifications' => $unreadCount,
            'pending_orders' => $pendingOrders,
            'open_tickets' => $openTickets,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        $user->unreadNotifications()->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Format a notification for the frontend.
     */
    protected function formatNotification($notification): array
    {
        $data = $notification->data ?? [];
        $type = $notification->type;

        // Extract the class name from the type
        $typeClass = class_basename($type);

        // Determine icon and color based on notification type
        $icon = 'bell';
        $color = 'info';
        $title = 'Notification';
        $message = $data['message'] ?? ($data['title'] ?? 'You have a new notification');

        if (str_contains($typeClass, 'Order')) {
            $icon = 'receipt';
            $color = 'warning';
            $title = 'New Order';
        } elseif (str_contains($typeClass, 'Support') || str_contains($typeClass, 'Ticket')) {
            $icon = 'headset';
            $color = 'info';
            $title = 'Support Ticket';
        } elseif (str_contains($typeClass, 'Payout')) {
            $icon = 'money-bill-wave';
            $color = 'success';
            $title = 'Payout';
        } elseif (str_contains($typeClass, 'Promotion') || str_contains($typeClass, 'Campaign')) {
            $icon = 'tags';
            $color = 'primary';
            $title = 'Promotion';
        } elseif (str_contains($typeClass, 'Refund')) {
            $icon = 'rotate-left';
            $color = 'danger';
            $title = 'Refund';
        }

        // Build URL if available
        $url = $data['url'] ?? null;
        if (!$url && isset($data['order_id'])) {
            $url = route('admin.orders.show', $data['order_id']);
        }
        if (!$url && isset($data['ticket_id'])) {
            $url = route('admin.support.show', $data['ticket_id']);
        }

        return [
            'id' => $notification->id,
            'type' => $typeClass,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'color' => $color,
            'url' => $url,
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at
                ? $notification->created_at->diffForHumans()
                : null,
            'raw_created_at' => $notification->created_at
                ? $notification->created_at->toDateTimeString()
                : null,
        ];
    }
}
