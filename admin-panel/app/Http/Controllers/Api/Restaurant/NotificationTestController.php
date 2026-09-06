<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Restaurant\Concerns\ResolvesRestaurantScope;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Fires a real test push to the signed-in restaurant device so an owner can
 * confirm new-order alerts actually arrive (sound + banner) on this phone.
 *
 *   POST /api/restaurant/notifications/test
 *   -> { data: { delivered, has_token, target_app } }
 *
 * The app also plays a local preview sound + local notification regardless, so
 * this endpoint only needs to exercise the server -> FCM path.
 */
class NotificationTestController extends Controller
{
    use ResolvesRestaurantScope;

    public function __construct(private readonly PushNotificationService $push)
    {
    }

    public function send(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $restaurant = $this->resolveSingleRestaurant($request, $user);

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $hasToken = $this->hasDeviceToken($user);

        $delivered = false;
        try {
            $delivered = $this->push->sendToUser(
                $user,
                'Test order alert',
                'This is a test notification for ' . ($restaurant?->name ?? 'your restaurant') . '. New orders will ring like this.',
                [
                    'type' => 'test',
                    'restaurant_id' => (string) ($restaurant?->id ?? ''),
                    'sound' => 'new_order',
                ],
                'restaurant',
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'delivered' => (bool) $delivered,
                'has_token' => $hasToken,
                'target_app' => 'restaurant',
                'hint' => $hasToken
                    ? ($delivered
                        ? 'Sent. If nothing arrives, check the phone\'s notification permission and battery optimisation for Swado Restaurant.'
                        : 'The push could not be delivered. Re-open the app to refresh the device token, then try again.')
                    : 'No device token is registered for this account yet. Fully close and re-open the app once to register it.',
            ],
        ]);
    }

    private function hasDeviceToken($user): bool
    {
        try {
            if (Schema::hasTable('device_tokens')) {
                return $user->deviceTokens()->exists();
            }
        } catch (\Throwable $e) {
            // fall through to column check
        }

        foreach (['fcm_token', 'device_token', 'push_token'] as $col) {
            if (Schema::hasColumn($user->getTable(), $col) && ! empty($user->{$col})) {
                return true;
            }
        }

        return false;
    }
}
