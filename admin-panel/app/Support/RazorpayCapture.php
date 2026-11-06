<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay reports a paid payment as "authorized" until it is captured. With auto-capture off on the
 * account (always the case for QR collections unless set), or in the moment before auto-capture runs, a
 * customer has been charged but the payment is not "captured" yet, so a strict check shows "payment
 * failed" and an authorized payment is refunded by Razorpay after a few days.
 *
 * Capturing an authorized payment whose amount is exactly what we expect settles it.
 */
class RazorpayCapture
{
    /**
     * @param array<string, mixed> $payment a Razorpay payment object
     * @return array<string, mixed> the payment, captured when that was possible
     */
    public static function ensureCaptured(array $payment, string $key, string $secret, ?int $expectedMinor = null): array
    {
        $id = (string) ($payment['id'] ?? '');
        if ($id === '' || strtolower((string) ($payment['status'] ?? '')) !== 'authorized') {
            return $payment;
        }
        $amount = (int) ($payment['amount'] ?? 0);
        if ($amount <= 0 || ($expectedMinor !== null && $amount !== $expectedMinor)) {
            return $payment;        // never capture an amount we did not expect
        }

        try {
            $response = Http::withBasicAuth($key, $secret)->acceptJson()->asJson()->timeout(20)
                ->post("https://api.razorpay.com/v1/payments/{$id}/capture", ['amount' => $amount, 'currency' => $payment['currency'] ?? 'INR']);
            if ($response->successful() && is_array($response->json())) {
                Log::info('Razorpay authorized payment captured.', ['payment_id' => $id, 'amount_minor' => $amount]);

                return $response->json();
            }
            Log::warning('Razorpay capture was refused.', ['payment_id' => $id, 'status' => $response->status(), 'response' => $response->json() ?: $response->body()]);
        } catch (\Throwable $e) {
            Log::warning('Razorpay capture failed.', ['payment_id' => $id, 'error' => $e->getMessage()]);
        }

        // Already captured by Razorpay's own auto-capture in the meantime: read the latest state.
        try {
            $latest = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(20)->get("https://api.razorpay.com/v1/payments/{$id}");
            if ($latest->successful() && is_array($latest->json())) {
                return $latest->json();
            }
        } catch (\Throwable) {
        }

        return $payment;
    }
}
