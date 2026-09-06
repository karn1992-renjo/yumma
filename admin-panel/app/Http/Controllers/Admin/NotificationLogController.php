<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:80'],
            'read' => ['nullable', 'in:yes,no'],
        ]);

        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->with('notifiable:id,name,email,phone')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('notifiable', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('data->type', $type))
            ->when(($filters['read'] ?? null) === 'yes', fn ($query) => $query->whereNotNull('read_at'))
            ->when(($filters['read'] ?? null) === 'no', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $types = DatabaseNotification::where('notifiable_type', User::class)
            ->latest()
            ->limit(500)
            ->pluck('data')
            ->map(fn ($data) => is_array($data) ? ($data['type'] ?? null) : null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('admin.notification-logs.index', [
            'notifications' => $notifications,
            'filters' => $filters,
            'types' => $types,
            'stats' => [
                'total' => DatabaseNotification::where('notifiable_type', User::class)->count(),
                'unread' => DatabaseNotification::where('notifiable_type', User::class)->whereNull('read_at')->count(),
                'today' => DatabaseNotification::where('notifiable_type', User::class)->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
