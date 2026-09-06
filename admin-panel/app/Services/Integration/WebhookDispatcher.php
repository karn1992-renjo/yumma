<?php

namespace App\Services\Integration;

use App\Jobs\DeliverWebhookJob;
use App\Models\AppSetting;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Fire-and-forget outbound events to the standalone Accounts/ and HRMS/ apps.
 * Completely inert unless the target's integration toggle is on, so `admin/`
 * behaves exactly as before by default.
 */
class WebhookDispatcher
{
    public static function enabled(string $target): bool
    {
        return (string) AppSetting::getValue("integration_{$target}_enabled", '0') === '1'
            && trim((string) AppSetting::getValue("integration_{$target}_url", '')) !== ''
            && trim((string) AppSetting::getValue("integration_{$target}_secret", '')) !== '';
    }

    public static function emit(string $target, string $topic, array $payload): void
    {
        if (! in_array($target, ['accounts', 'hrms'], true) || ! self::enabled($target)) {
            return;
        }

        $delivery = WebhookDelivery::create([
            'event_id' => (string) Str::uuid(),
            'target' => $target,
            'topic' => $topic,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        DeliverWebhookJob::dispatch($delivery->id);
    }
}
