<?php

namespace App\Services;

use App\Models\RestaurantOnboarding;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Support\Facades\Log;

class RestaurantOnboardingNotificationService
{
    public function driver(RestaurantOnboarding $onboarding, string $title, string $body, string $type): void
    {
        $driver = $onboarding->driver;
        if (! $driver) {
            return;
        }

        $payload = [
            'type' => $type,
            'target_app' => 'driver',
            'restaurant_onboarding_id' => (string) $onboarding->id,
            'application_number' => $onboarding->application_number,
            'deep_link' => '/driver/restaurant-onboardings/' . $onboarding->id,
        ];

        try {
            $driver->notify(new AppDatabaseNotification($title, $body, $payload));
            app(PushNotificationService::class)->sendToUser($driver, $title, $body, $payload, 'driver');
        } catch (\Throwable $e) {
            Log::warning('Restaurant onboarding driver notification failed.', [
                'restaurant_onboarding_id' => $onboarding->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
