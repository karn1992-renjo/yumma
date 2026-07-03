<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FirebaseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function __construct(
        protected PushNotificationService $pushNotificationService
    ) {
    }

    public function index()
    {
        $broadcasts = PushBroadcast::with('sender')
            ->latest()
            ->paginate(15);

        return view('admin.push-notifications.index', [
            'broadcasts' => $broadcasts,
            'roleLabels' => $this->roleLabels(),
        ]);
    }

    public function create()
    {
        return view('admin.push-notifications.create', [
            'roleLabels' => $this->roleLabels(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'audience_type' => 'required|in:all,roles',
            'audience_roles' => 'nullable|array',
            'audience_roles.*' => 'in:customer,restaurant_owner,restaurant_staff,delivery_partner',
            'deep_link' => 'nullable|string|max:2048',
            'image_url' => 'nullable|url|max:2048',
            'data_key' => 'nullable|array',
            'data_key.*' => 'nullable|string|max:100',
            'data_value' => 'nullable|array',
            'data_value.*' => 'nullable|string|max:500',
        ]);

        if ($validated['audience_type'] === 'roles' && empty($validated['audience_roles'])) {
            return back()
                ->withErrors(['audience_roles' => 'Select at least one role for role-wise targeting.'])
                ->withInput();
        }

        $broadcast = PushBroadcast::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'audience_type' => $validated['audience_type'],
            'audience_roles' => $validated['audience_type'] === 'roles'
                ? array_values($validated['audience_roles'] ?? [])
                : [],
            'deep_link' => $validated['deep_link'] ?? null,
            'data_payload' => array_filter(array_merge(
                $this->buildDataPayload(
                    $request->input('data_key', []),
                    $request->input('data_value', [])
                ),
                ['image_url' => $validated['image_url'] ?? null]
            ), fn ($value) => filled($value)),
            'status' => 'processing',
            'sent_by' => auth()->id(),
        ]);

        $this->pushNotificationService->sendBroadcast($broadcast);

        return redirect()
            ->route('admin.push-notifications.index')
            ->with('success', 'Push notification sent successfully.');
    }

    public function sendTest(Request $request)
    {
        $validated = $request->validate([
            'target_app' => 'required|in:customer,restaurant,driver',
            'phone' => 'required|string|max:40',
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'deep_link' => 'nullable|string|max:2048',
            'image_url' => 'nullable|url|max:2048',
        ]);

        $user = $this->findTestRecipient(
            $validated['target_app'],
            $validated['phone']
        );

        if (! $user) {
            return redirect()
                ->route('admin.settings.notifications')
                ->withInput()
                ->with('error', 'No matching user was found for the selected app and mobile number.');
        }

        $token = $user->fcmTokenForApp($validated['target_app']);

        if (! filled($token)) {
            return redirect()
                ->route('admin.settings.notifications')
                ->withInput()
                ->with('error', 'The selected user does not have an FCM token yet. Open the app on that device and log in first.');
        }

        $firebase = new FirebaseHelper();
        $sent = $firebase->sendToDevice(
            $token,
            $validated['title'],
            $validated['body'],
            array_filter([
                'type' => 'admin_broadcast',
                'test_push' => '1',
                'target_app' => $validated['target_app'],
                'target_user_id' => (string) $user->id,
                'deep_link' => $validated['deep_link'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ], fn ($value) => filled($value))
        );

        if (! $sent) {
            $reason = $firebase->diagnostics()['failure_reason'] ?? 'Firebase push delivery failed.';

            return redirect()
                ->route('admin.settings.notifications')
                ->withInput()
                ->with('error', $reason);
        }

        return redirect()
            ->route('admin.settings.notifications')
            ->with('success', 'Test push sent to ' . $user->name . ' (' . $user->phone . ') on the ' . $validated['target_app'] . ' app.');
    }

    protected function buildDataPayload(array $keys, array $values): array
    {
        $payload = [];

        foreach ($keys as $index => $key) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $payload[$normalizedKey] = (string) ($values[$index] ?? '');
        }

        return $payload;
    }

    protected function roleLabels(): array
    {
        return [
            'customer' => 'Customers',
            'restaurant_owner' => 'Restaurant Owners',
            'restaurant_staff' => 'Restaurant Staff',
            'delivery_partner' => 'Drivers',
        ];
    }

    protected function findTestRecipient(string $targetApp, string $phone): ?User
    {
        $normalizedPhone = PhoneNumber::normalize(
            $phone,
            AppSetting::getValue('default_mobile_country_code', '+91')
        );
        $query = User::query()
            ->where('phone', $normalizedPhone)
            ->where('is_active', true);

        return match ($targetApp) {
            'customer' => $query->role('customer')->first(),
            'restaurant' => $query->whereHas('roles', function ($builder) {
                $builder->whereIn('name', ['restaurant_owner', 'restaurant_staff']);
            })->first(),
            'driver' => $query->role('delivery_partner')->first(),
            default => null,
        };
    }
}
