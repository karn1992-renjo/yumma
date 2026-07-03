<?php

namespace App\Services;

use App\Helpers\FirebaseHelper;
use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Support\Collection;

class PushNotificationService
{
    public function sendBroadcast(PushBroadcast $broadcast): PushBroadcast
    {
        $firebase = new FirebaseHelper();

        if (! $firebase->isConfigured()) {
            $broadcast->update([
                'status' => 'failed',
                'failure_reason' => 'Firebase push notifications are not configured.',
                'sent_at' => now(),
            ]);

            return $broadcast->fresh('sender');
        }

        $users = $this->resolveAudienceUsers($broadcast);
        $targetApps = $this->targetAppsForBroadcast($broadcast);
        $tokens = $users->flatMap(function (User $user) use ($targetApps) {
                if ($targetApps->isEmpty()) {
                    return collect([
                        $user->customer_fcm_token,
                        $user->restaurant_fcm_token,
                        $user->driver_fcm_token,
                        $user->fcm_token,
                    ]);
                }

                return $targetApps->map(fn ($targetApp) => $user->fcmTokenForApp($targetApp));
            })
            ->filter(fn ($token) => filled($token))
            ->unique()
            ->values()
            ->all();

        $payload = array_merge($broadcast->data_payload ?? [], [
            'type' => 'admin_broadcast',
            'broadcast_id' => (string) $broadcast->id,
        ]);

        if (filled($broadcast->deep_link)) {
            $payload['deep_link'] = $broadcast->deep_link;
        }

        if (empty($tokens)) {
            $broadcast->update([
                'status' => 'failed',
                'recipients_count' => $users->count(),
                'token_count' => 0,
                'delivered_count' => 0,
                'failed_count' => 0,
                'failure_reason' => $users->isEmpty()
                    ? 'No users matched the selected audience.'
                    : 'No registered device tokens found for the selected audience. Ask users to open the latest app build once.',
                'sent_at' => now(),
            ]);

            return $broadcast->fresh('sender');
        }

        $result = $firebase->sendToDevices(
            $tokens,
            $broadcast->title,
            $broadcast->body,
            $payload
        );

        $broadcast->update([
            'status' => $result['failure'] > 0 && $result['success'] === 0 ? 'failed' : 'sent',
            'recipients_count' => $users->count(),
            'token_count' => count($tokens),
            'delivered_count' => $result['success'],
            'failed_count' => $result['failure'],
            'failure_reason' => $result['failure_reason'],
            'sent_at' => now(),
        ]);

        return $broadcast->fresh('sender');
    }

    protected function resolveAudienceUsers(PushBroadcast $broadcast): Collection
    {
        $query = User::query()
            ->where('is_active', true);

        if ($broadcast->audience_type === 'roles' && ! empty($broadcast->audience_roles)) {
            $roles = collect($broadcast->audience_roles)
                ->filter()
                ->values()
                ->all();

            $query->whereHas('roles', function ($roleQuery) use ($roles) {
                $roleQuery->whereIn('name', $roles);
            });
        }

        return $query->get([
            'id',
            'fcm_token',
            'customer_fcm_token',
            'restaurant_fcm_token',
            'driver_fcm_token',
        ]);
    }

    private function targetAppsForBroadcast(PushBroadcast $broadcast): Collection
    {
        if ($broadcast->audience_type !== 'roles' || empty($broadcast->audience_roles)) {
            return collect();
        }

        return collect($broadcast->audience_roles)
            ->map(fn ($role) => $this->targetAppForRole((string) $role))
            ->filter()
            ->unique()
            ->values();
    }

    private function targetAppForRole(string $role): ?string
    {
        $role = strtolower(str_replace('-', '_', $role));

        if (str_contains($role, 'restaurant') || str_contains($role, 'owner') || str_contains($role, 'staff')) {
            return 'restaurant';
        }

        if (str_contains($role, 'driver') || str_contains($role, 'delivery')) {
            return 'driver';
        }

        if (str_contains($role, 'customer') || str_contains($role, 'user')) {
            return 'customer';
        }

        return null;
    }
}
