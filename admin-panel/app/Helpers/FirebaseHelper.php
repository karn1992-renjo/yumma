<?php

namespace App\Helpers;

use App\Models\AppSetting;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Storage;

class FirebaseHelper
{
    protected $messaging;
    protected bool $configured = false;
    protected ?string $failureReason = null;
    
    public function __construct()
    {
        $credentials = $this->resolveCredentialsPath();
        $enabled = $this->resolveEnabledFlag();

        if (! class_exists(Factory::class)) {
            $this->failureReason = 'Firebase PHP package is not installed. Run composer install/update for kreait/firebase-php.';
            return;
        }

        if (! $enabled || empty($credentials) || ! file_exists($credentials)) {
            $this->failureReason = ! $enabled
                ? 'Firebase is disabled in admin settings.'
                : 'Firebase service account JSON is missing or not readable.';
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentials);
            $this->messaging = $factory->createMessaging();
            $this->configured = true;
        } catch (\Throwable $e) {
            $this->failureReason = $e->getMessage();
            \Log::error("Firebase initialization failed: " . $e->getMessage());
        }
    }

    public function isConfigured(): bool
    {
        return $this->configured && $this->messaging !== null;
    }

    public function diagnostics(): array
    {
        $settings = AppSetting::all()->pluck('value', 'key');
        $serviceAccountPath = $this->resolveCredentialsPath();

        return [
            'enabled' => $this->resolveEnabledFlag(),
            'configured' => $this->isConfigured(),
            'project_id' => $settings->get('firebase_project_id'),
            'service_account_path' => $settings->get('firebase_service_account_path'),
            'resolved_service_account_path' => $serviceAccountPath,
            'service_account_exists' => filled($serviceAccountPath) && file_exists($serviceAccountPath),
            'database_url' => $settings->get('firebase_database_url'),
            'storage_bucket' => $settings->get('firebase_storage_bucket'),
            'messaging_sender_id' => $settings->get('firebase_messaging_sender_id'),
            'app_id' => $settings->get('firebase_app_id'),
            'failure_reason' => $this->failureReason,
        ];
    }
    
    public function sendToDevice($token, $title, $body, $data = [])
    {
        if (! $this->isConfigured()) {
            \Log::warning('Firebase send skipped because Firebase is not configured.');
            return false;
        }

        try {
            $channelId = (string) ($data['android_channel_id'] ?? '');
            if ($channelId === '') {
                $type = strtolower((string) ($data['type'] ?? ''));
                $role = strtolower((string) ($data['role'] ?? ''));
                $channelId = $role === 'customer' || str_contains($type, 'order_status')
                    ? 'order_status_channel_custom'
                    : 'incoming_order_channel';
            }

            $imageUrl = $this->notificationImageUrl($data);
            $data = $this->withNotificationData($data, $title, $body, $imageUrl);
            $message = CloudMessage::withTarget('token', $token)
                ->withData($this->sanitizeData($data))
                ->withAndroidConfig($this->androidConfig($channelId, $imageUrl));

            if (filled($imageUrl)) {
                $message = $message->withApnsConfig($this->apnsConfig($imageUrl));
            }

            if (! $this->isIncomingOrderPayload($data) && ! $this->isDataOnlyPayload($data)) {
                $message = $message->withNotification(Notification::create($title, $body, $imageUrl));
            }
                
            $this->messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Firebase send failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        if (! $this->isConfigured()) {
            \Log::warning('Firebase topic send skipped because Firebase is not configured.');
            return false;
        }

        try {
            $imageUrl = $this->notificationImageUrl($data);
            $data = $this->withNotificationData($data, $title, $body, $imageUrl);
            $notification = Notification::create($title, $body, $imageUrl);
            
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($this->sanitizeData($data))
                ->withAndroidConfig($this->androidConfig('incoming_order_channel', $imageUrl));

            if (filled($imageUrl)) {
                $message = $message->withApnsConfig($this->apnsConfig($imageUrl));
            }
                
            $this->messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Firebase send to topic failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function subscribeToTopic($token, $topic)
    {
        if (! $this->isConfigured()) {
            \Log::warning('Firebase topic subscribe skipped because Firebase is not configured.');
            return false;
        }

        try {
            $this->messaging->subscribeToTopic($topic, $token);
            return true;
        } catch (\Exception $e) {
            \Log::error("Firebase subscribe failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendToDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = collect($tokens)
            ->filter(fn ($token) => filled($token))
            ->unique()
            ->values()
            ->all();

        if (! $this->isConfigured()) {
            return [
                'success' => 0,
                'failure' => count($tokens),
                'failure_reason' => 'Firebase is not configured.',
            ];
        }

        if (empty($tokens)) {
            return [
                'success' => 0,
                'failure' => 0,
                'failure_reason' => null,
            ];
        }

        $channelId = (string) ($data['android_channel_id'] ?? '');
        if ($channelId === '') {
            $role = strtolower((string) ($data['role'] ?? ''));
            $type = strtolower((string) ($data['type'] ?? ''));
            $channelId = $role === 'customer' || str_contains($type, 'order_status')
                ? 'order_status_channel_custom'
                : 'default_notification_channel_custom';
        }
        $imageUrl = $this->notificationImageUrl($data);
        $data = $this->withNotificationData($data, $title, $body, $imageUrl);

        $message = CloudMessage::new()
            ->withData($this->sanitizeData($data))
            ->withAndroidConfig($this->androidConfig($channelId, $imageUrl));

        if (filled($imageUrl)) {
            $message = $message->withApnsConfig($this->apnsConfig($imageUrl));
        }

        if (! $this->isIncomingOrderPayload($data) && ! $this->isDataOnlyPayload($data)) {
            $message = $message->withNotification(Notification::create($title, $body, $imageUrl));
        }

        $success = 0;
        $failure = 0;

        try {
            foreach (array_chunk($tokens, 500) as $tokenChunk) {
                $report = $this->messaging->sendMulticast($message, $tokenChunk);
                $success += $report->successes()->count();
                $failure += $report->failures()->count();
            }

            return [
                'success' => $success,
                'failure' => $failure,
                'failure_reason' => null,
            ];
        } catch (\Throwable $e) {
            \Log::error("Firebase multicast send failed: " . $e->getMessage());

            return [
                'success' => $success,
                'failure' => max(count($tokens), $failure),
                'failure_reason' => $e->getMessage(),
            ];
        }
    }

    private function sanitizeData(array $data): array
    {
        $reserved = [
            'contentAvailable',
            'content_available',
            'mutableContent',
            'mutable_content',
        ];

        foreach ($reserved as $key) {
            unset($data[$key]);
        }

        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value)])
            ->all();
    }

    private function withNotificationData(array $data, string $title, string $body, ?string $imageUrl = null): array
    {
        $payload = array_merge([
            'notification_title' => $title,
            'notification_body' => $body,
        ], $data);

        if (filled($imageUrl)) {
            $payload['image_url'] = $imageUrl;
            $payload['image'] = $imageUrl;
        }

        return $payload;
    }

    private function notificationImageUrl(array $data): ?string
    {
        $imageUrl = trim((string) ($data['image_url'] ?? $data['image'] ?? ''));

        return filter_var($imageUrl, FILTER_VALIDATE_URL) ? $imageUrl : null;
    }

    private function androidConfig(string $channelId, ?string $imageUrl = null): array
    {
        return [
            'priority' => 'high',
            'notification' => array_filter([
                'channel_id' => $channelId,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => $this->soundForChannel($channelId),
                'notification_priority' => 'PRIORITY_MAX',
                'visibility' => 'PUBLIC',
                'image' => $imageUrl,
            ], fn ($value) => filled($value)),
        ];
    }

    private function apnsConfig(?string $imageUrl = null): array
    {
        if (! filled($imageUrl)) {
            return [];
        }

        return [
            'payload' => [
                'aps' => [
                    'mutable-content' => 1,
                ],
            ],
            'fcm_options' => [
                'image' => $imageUrl,
            ],
        ];
    }

    private function isIncomingOrderPayload(array $data): bool
    {
        $type = strtolower(str_replace('-', '_', (string) ($data['type'] ?? '')));
        $event = strtolower(str_replace('-', '_', (string) ($data['event'] ?? '')));
        $role = strtolower((string) ($data['role'] ?? ''));

        return in_array($role, ['restaurant', 'driver'], true)
            && (
                in_array($type, ['new_order', 'driver_order_assigned'], true)
                || in_array($event, ['new_order', 'driver_order_assigned'], true)
            );
    }

    private function isDataOnlyPayload(array $data): bool
    {
        return filter_var($data['data_only'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($data['suppress_notification'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function soundForChannel(string $channelId): string
    {
        return str_contains($channelId, '_custom') ? 'custom_push' : 'default';
    }

    private function resolveEnabledFlag(): bool
    {
        $setting = AppSetting::getValue('firebase_enabled');
        if ($setting !== null && $setting !== '') {
            return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
        }

        return filter_var(config('services.firebase.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function resolveCredentialsPath(): ?string
    {
        $storedPath = AppSetting::getValue('firebase_service_account_path');
        if (filled($storedPath)) {
            $storedPath = ltrim($storedPath, '/\\');

            if (Storage::disk('local')->exists($storedPath)) {
                return Storage::disk('local')->path($storedPath);
            }

            $legacyPath = storage_path('app/' . $storedPath);
            if (file_exists($legacyPath)) {
                return $legacyPath;
            }
        }

        $configPath = config('firebase.credentials');
        if (filled($configPath) && file_exists($configPath)) {
            return $configPath;
        }

        return null;
    }
}
