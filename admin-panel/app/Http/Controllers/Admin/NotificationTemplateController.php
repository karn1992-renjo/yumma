<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'in:push,sms,database'],
            'group' => ['nullable', 'string', 'max:32'],
        ]);

        $templates = NotificationTemplate::query()
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($filters['group'] ?? null, fn ($query, $group) => $query->where('group', $group))
            ->orderBy('group')
            ->orderBy('key')
            ->get();

        return view('admin.notification-templates.index', [
            'templates' => $templates,
            'filters' => $filters,
            'groups' => NotificationTemplate::query()->distinct()->orderBy('group')->pluck('group'),
        ]);
    }

    public function edit(NotificationTemplate $notificationTemplate)
    {
        return view('admin.notification-templates.edit', [
            'template' => $notificationTemplate,
        ]);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate)
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $notificationTemplate->update([
            'title' => $notificationTemplate->title !== null ? ($validated['title'] ?? '') : null,
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.notification-templates.index')
            ->with('success', 'Notification template updated successfully.');
    }
}
