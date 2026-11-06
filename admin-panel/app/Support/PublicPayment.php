<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Pay-then-order for a cart built in WhatsApp. The app's checkout is "pay first, then the order is
 * created with the payment proof", so the order does not exist until the payment is confirmed. This
 * keeps the cart safely server-side under a random token, and the customer reaches it through a signed,
 * expiring link. Card and UPI details only ever go to the payment gateway's own checkout window.
 */
class PublicPayment
{
    public const MINUTES_VALID = 30;

    /** Stores the cart and returns the signed payment link. */
    public static function createSession(User $user, array $orderPayload, float $amount, string $restaurantName, string $contactPhone): string
    {
        $token = Str::random(40);
        Cache::put(self::key($token), [
            'user_id' => $user->id, 'order_payload' => $orderPayload, 'amount' => round($amount, 2),
            'restaurant' => $restaurantName, 'contact_phone' => $contactPhone, 'checkout_token' => null,
        ], now()->addMinutes(self::MINUTES_VALID));

        return self::url($token);
    }

    /** @return array<string, mixed>|null */
    public static function session(string $token): ?array
    {
        $session = Cache::get(self::key($token));

        return is_array($session) ? $session : null;
    }

    public static function update(string $token, array $changes): void
    {
        if ($session = self::session($token)) {
            Cache::put(self::key($token), array_merge($session, $changes), now()->addMinutes(self::MINUTES_VALID));
        }
    }

    public static function forget(string $token): void
    {
        Cache::forget(self::key($token));
    }

    public static function url(string $token): string
    {
        return URL::temporarySignedRoute('order.pay', now()->addMinutes(self::MINUTES_VALID), ['token' => $token]);
    }

    public static function startUrl(string $token): string
    {
        return URL::temporarySignedRoute('order.pay.start', now()->addMinutes(self::MINUTES_VALID), ['token' => $token]);
    }

    public static function completeUrl(string $token): string
    {
        return URL::temporarySignedRoute('order.pay.complete', now()->addMinutes(self::MINUTES_VALID), ['token' => $token]);
    }

    private static function key(string $token): string
    {
        return 'wa_pay:' . $token;
    }
}
