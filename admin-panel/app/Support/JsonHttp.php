<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * A JSON encoder for payloads containing money-like float values (order
 * amounts, recharge amounts, ...) posted to gateways that reject anything
 * but a clean 2-decimal number -- Cashfree specifically, confirmed against
 * a real production log ("order_amount: Invalid amount entered").
 *
 * Why not just json_encode()/Http::asJson()? PHP's float-to-JSON behavior
 * is controlled by the `serialize_precision` ini setting, which is NOT
 * guaranteed to be `-1` (PHP's modern default) on every host -- confirmed
 * live: swaad's prod server has it set differently, which makes
 * json_encode() dump a float's full raw IEEE-754 binary representation
 * instead of a clean decimal (a real 164.98 was sent as
 * 164.979999999999989768184605054557323455810546875). A first attempt at
 * this fix used ini_set('serialize_precision', -1) to force the behavior
 * for one encode call, which worked, but still depends on every server
 * respecting that ini_set() the same way. This version has NO dependency
 * on any server setting at all: every float in the payload is formatted as
 * a decimal STRING first via number_format() (string formatting has no
 * serialize_precision concept whatsoever), then the surrounding quotes are
 * stripped from the final JSON text so the gateway still receives a JSON
 * *number*, not a string, matching what its API docs specify.
 */
class JsonHttp
{
    private const MARKER_PREFIX = '@@jsonhttp_decimal:';
    private const MARKER_SUFFIX = '@@';

    public static function post(PendingRequest $request, string $url, array $payload): Response
    {
        return $request->withBody(self::encode($payload), 'application/json')->post($url);
    }

    public static function encode(array $payload): string
    {
        $marked = self::markFloats($payload);
        $json = json_encode($marked);

        return preg_replace(
            '/"' . preg_quote(self::MARKER_PREFIX, '/') . '(-?\d+\.\d{2})' . preg_quote(self::MARKER_SUFFIX, '/') . '"/',
            '$1',
            $json
        );
    }

    private static function markFloats(mixed $value): mixed
    {
        if (is_float($value)) {
            return self::MARKER_PREFIX . number_format($value, 2, '.', '') . self::MARKER_SUFFIX;
        }

        if (is_array($value)) {
            return array_map([self::class, 'markFloats'], $value);
        }

        return $value;
    }
}
