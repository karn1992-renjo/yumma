<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * Login-free live tracking link for one order (used in WhatsApp). The link is signed with the
 * app key and expires, so it cannot be guessed or edited to look at another order.
 */
class PublicTracking
{
    public const HOURS_VALID = 12;

    public static function url(Order $order): string
    {
        return URL::temporarySignedRoute('track.public', now()->addHours(self::HOURS_VALID), ['order' => $order->id]);
    }

    public static function dataUrl(Order $order): string
    {
        return URL::temporarySignedRoute('track.public.data', now()->addHours(self::HOURS_VALID), ['order' => $order->id]);
    }

    /** A plain Google Maps link that opens the rider's position in any map app. */
    public static function mapsLink(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
    }
}
