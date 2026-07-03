<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'notifications' => $user->notifications()
                    ->latest()
                    ->limit($limit)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'data' => $notification->data,
                        'read_at' => optional($notification->read_at)->toIso8601String(),
                        'created_at' => optional($notification->created_at)->toIso8601String(),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function markRead(Request $request, ?string $id = null)
    {
        $query = $request->user()->unreadNotifications();

        if ($id) {
            $query->whereKey($id);
        }

        $query->get()->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
        ]);
    }

    public function clear(Request $request)
    {
        $request->user()->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared.',
        ]);
    }
}
