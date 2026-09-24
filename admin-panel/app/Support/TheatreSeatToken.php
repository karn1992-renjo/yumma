<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * swaad-only: signs and verifies the seat identity printed on a theatre
 * seat's QR sticker.
 *
 * Presence at the theatre is proven one of two ways (see the
 * movie-theatre-in-seat-ordering project memory): a GPS geofence for the
 * app's manual pick-a-seat flow, or one of these signatures for the QR
 * flow. A QR is the stronger of the two in practice -- the sticker is
 * physically on the armrest, so scanning it is better evidence of being in
 * that seat than a spoofable phone coordinate -- which is why a valid
 * signature is accepted INSTEAD of the geofence rather than in addition to
 * it. Without a signature anyone could type someone else's seat into the
 * URL and have food delivered to it.
 *
 * Stateless by design: nothing is stored per seat, so a theatre can print
 * stickers for a screen the moment its seat map is saved, and re-print a
 * single damaged sticker years later without any lookup. Rotating APP_KEY
 * invalidates every printed sticker, which is the intended (and only)
 * revocation mechanism.
 */
class TheatreSeatToken
{
    /**
     * Short enough to keep the QR's error-correction density low (a sticker
     * gets scanned in the dark, at an angle, by a phone camera), long enough
     * that guessing one is hopeless: 10 hex chars = 40 bits, and a wrong
     * guess only ever buys the attacker the right to pay for someone else's
     * snacks.
     */
    private const LENGTH = 10;

    public static function sign(int $theatreId, int $screenId, string $seatLabel): string
    {
        return substr(
            hash_hmac('sha256', self::payload($theatreId, $screenId, $seatLabel), self::key()),
            0,
            self::LENGTH
        );
    }

    public static function verify(int $theatreId, int $screenId, string $seatLabel, ?string $signature): bool
    {
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($theatreId, $screenId, $seatLabel), $signature);
    }

    /**
     * The path segment a QR encodes: everything needed to identify the seat
     * plus its signature, so the landing route stays a plain GET with no
     * query string to lose when a scanner app rewrites the URL.
     */
    public static function path(int $theatreId, int $screenId, string $seatLabel): string
    {
        return $theatreId . '/' . $screenId . '/' . rawurlencode($seatLabel)
            . '/' . self::sign($theatreId, $screenId, $seatLabel);
    }

    public static function url(int $theatreId, int $screenId, string $seatLabel): string
    {
        return url('/t/' . self::path($theatreId, $screenId, $seatLabel));
    }

    private static function payload(int $theatreId, int $screenId, string $seatLabel): string
    {
        // Upper-cased so a sticker reprinted from a differently-cased seat
        // label still verifies -- seat labels are row letter + number and
        // are compared case-insensitively everywhere else too.
        return 'theatre-seat:' . $theatreId . ':' . $screenId . ':' . strtoupper(trim($seatLabel));
    }

    private static function key(): string
    {
        $key = (string) Config::get('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7)) ?: $key;
        }

        return $key;
    }
}
