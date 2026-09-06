<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $targetApp = $this->targetApp($request);
        $limit = min(max((int) $request->query('limit', 30), 1), 100);
        $user = $request->user();
        $notifications = $this->notificationsFor($request, $targetApp);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => (clone $notifications)->whereNull('read_at')->count(),
                'notifications' => $notifications
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
        $query = $this->notificationsFor($request, $this->targetApp($request))
            ->whereNull('read_at');

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
        $this->notificationsFor($request, $this->targetApp($request))->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared.',
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $deleted = $this->notificationsFor($request, $this->targetApp($request))
            ->whereKey($id)
            ->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0
                ? 'Notification removed.'
                : 'Notification not found.',
        ], $deleted > 0 ? 200 : 404);
    }

    public function preferences(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'notify_order_updates' => (bool) $user->notify_order_updates,
                'notify_offers_promotions' => (bool) $user->notify_offers_promotions,
            ],
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'notify_order_updates' => ['sometimes', 'boolean'],
            'notify_offers_promotions' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->fill($validated)->save();

        return response()->json([
            'success' => true,
            'data' => [
                'notify_order_updates' => (bool) $user->notify_order_updates,
                'notify_offers_promotions' => (bool) $user->notify_offers_promotions,
            ],
        ]);
    }

    private function targetApp(Request $request): string
    {
        return $request->validate([
            'target_app' => ['required', 'in:customer,restaurant,driver'],
        ])['target_app'];
    }

    private function notificationsFor(Request $request, string $targetApp)
    {
        return $request->user()->notifications()
            ->where(function ($query) use ($targetApp) {
                $query->where('data->role', $targetApp)
                    ->orWhere('data->target_app', $targetApp)
                    ->orWhere('data->requester_role', $targetApp);

            });
    }
}
