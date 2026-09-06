<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * HMAC-SHA256 request signing shared by admin/ (sender) and the Accounts/ /
 * HRMS/ apps (receivers). Header set: X-Swado-Timestamp, X-Swado-Signature
 * (= hex hmac of "{timestamp}.{rawBody}").
 */
class WebhookSignature
{
    public const SKEW_SECONDS = 300;

    /** @return array<string,string> headers to attach to the outbound request */
    public static function headers(string $rawBody, string $secret): array
    {
        $ts = (string) time();

        return [
            'X-Swado-Timestamp' => $ts,
            'X-Swado-Signature' => hash_hmac('sha256', $ts . '.' . $rawBody, $secret),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    public static function verify(Request $request, string $secret): bool
    {
        $ts = (string) $request->header('X-Swado-Timestamp', '');
        $sig = (string) $request->header('X-Swado-Signature', '');
        if ($ts === '' || $sig === '' || $secret === '') {
            return false;
        }
        if (abs(time() - (int) $ts) > self::SKEW_SECONDS) {
            return false;
        }
        $expected = hash_hmac('sha256', $ts . '.' . $request->getContent(), $secret);

        return hash_equals($expected, $sig);
    }
}
