<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\MediaStorage;
use App\Services\OrderEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateController extends Controller
{
    public function index()
    {
        return view('admin.email-templates.index', [
            'templates' => NotificationTemplate::query()
                ->where('channel', 'email')
                ->orderBy('label')
                ->get(),
        ]);
    }

    public function edit(NotificationTemplate $emailTemplate)
    {
        abort_unless($emailTemplate->channel === 'email', 404);

        return view('admin.email-templates.edit', [
            'template' => $emailTemplate,
            'sample' => app(OrderEmailService::class)->sampleVariables(),
        ]);
    }

    public function update(Request $request, NotificationTemplate $emailTemplate)
    {
        abort_unless($emailTemplate->channel === 'email', 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:60000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $emailTemplate->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.email-templates.edit', $emailTemplate)
            ->with('success', 'Email template saved.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:3072'],
        ]);

        try {
            $path = MediaStorage::store($request->file('image'), 'email-templates');
        } catch (\Throwable $e) {
            Log::warning('Email template image upload failed.', ['message' => $e->getMessage()]);

            return response()->json(['message' => 'Upload failed. Please try again.'], 422);
        }

        // Absolute URL -- images in email clients cannot resolve relative paths.
        return response()->json(['url' => (string) url(MediaStorage::url($path))]);
    }

    public function sendTest(Request $request, NotificationTemplate $emailTemplate)
    {
        abort_unless($emailTemplate->channel === 'email', 404);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $service = app(OrderEmailService::class);
        $vars = $service->sampleVariables();
        [$subject, $body] = $service->render($vars);

        try {
            Mail::html($body, function ($message) use ($validated, $subject) {
                $message->to($validated['email'])->subject('[Test] ' . $subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Email template test send failed.', ['message' => $e->getMessage()]);

            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Test email sent to ' . $validated['email'] . '.');
    }
}
