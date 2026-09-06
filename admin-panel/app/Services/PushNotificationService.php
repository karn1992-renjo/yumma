<?php

namespace App\Services;

use App\Helpers\FirebaseHelper;
use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PushNotificationService
{
    /**
     * Single-recipient send for personalized content (cart reminders,
     * reorder nudges) -- distinct from sendBroadcast(), which targets a
     * whole role and creates a PushBroadcast audit row. Callers that need
     * an audit trail for a personalized send should log it themselves
     * (see App\Services\CartRecoveryService / App\Services\ReorderNudgeService).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [], string $targetApp = 'customer'): bool
    {
        $firebase = new FirebaseHelper();
        if (! $firebase->isConfigured()) {
            return false;
        }

        $token = $user->fcmTokenForApp($targetApp);
        if (! $token) {
            return false;
        }

        return $firebase->sendToDevice($token, $title, $body, $data);
    }

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

        if ($targetApps->count() === 1) {
            $targetApp = $targetApps->first();
            $payload['target_app'] = $targetApp;

            if ($targetApp === 'customer') {
                $payload['data_only'] = '1';
                if ($this->hasImagePayload($payload)) {
                    $payload['image_banner'] = '1';
                }
            }
        }

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

        // AI-generated broadcasts are informational/marketing, not
        // transactional order alerts -- honor the same opt-out a user would
        // expect for promotional pushes. Admin-authored broadcasts keep
        // their existing (unfiltered) behavior; this only narrows the
        // audience for broadcasts the AI itself created.
        if ($broadcast->source === 'ai' && Schema::hasColumn('users', 'notify_offers_promotions')) {
            $query->where('notify_offers_promotions', true);
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

    private function hasImagePayload(array $payload): bool
    {
        return filled($payload['image_url'] ?? null) || filled($payload['image'] ?? null);
    }
}