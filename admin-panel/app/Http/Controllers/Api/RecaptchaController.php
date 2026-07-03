<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaController extends Controller
{
    /**
     * Validate reCAPTCHA v3 token
     */
    public function validateRecaptcha(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'score_threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        $recaptchaSecret = config('services.recaptcha.secret_key');
        $scoreThreshold = $request->input('score_threshold', 0.5);

        if (!$recaptchaSecret) {
            Log::warning('reCAPTCHA secret key not configured');
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA not configured',
            ], 500);
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $request->input('token'),
            ]);

            $data = $response->json();

            // Check if response is successful and score is above threshold
            $isValid = isset($data['success']) && $data['success'] === true;
            $score = $data['score'] ?? 0;
            $isAboveThreshold = $score >= $scoreThreshold;

            if ($isValid && $isAboveThreshold) {
                return response()->json([
                    'success' => true,
                    'valid' => true,
                    'score' => $score,
                    'action' => $data['action'] ?? null,
                    'challenge_ts' => $data['challenge_ts'] ?? null,
                ]);
            }

            Log::warning('reCAPTCHA validation failed', [
                'valid' => $isValid,
                'score' => $score,
                'threshold' => $scoreThreshold,
                'response' => $data,
            ]);

            return response()->json([
                'success' => false,
                'valid' => false,
                'score' => $score,
                'message' => 'reCAPTCHA validation failed. Score: ' . $score,
            ]);

        } catch (\Exception $e) {
            Log::error('reCAPTCHA validation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error validating reCAPTCHA',
            ], 500);
        }
    }

    /**
     * Validate OTP with reCAPTCHA protection
     */
    public function validateOtpWithRecaptcha(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
            'recaptcha_token' => 'required|string',
        ]);

        // First, validate reCAPTCHA
        $recaptchaValidation = $this->validateRecaptchaToken(
            $request->input('recaptcha_token'),
            0.5
        );

        if (!$recaptchaValidation['valid']) {
            Log::warning('OTP validation rejected due to reCAPTCHA', [
                'phone' => $request->input('phone'),
                'score' => $recaptchaValidation['score'] ?? 0,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Suspicious activity detected. Please try again.',
            ], 403);
        }

        // Continue with OTP validation
        // This would be implemented in your OTP service
        // For now, return a placeholder
        
        return response()->json([
            'success' => false,
            'message' => 'OTP validation not implemented in this method',
        ]);
    }

    /**
     * Helper method to validate reCAPTCHA token
     */
    private function validateRecaptchaToken(string $token, float $threshold = 0.5): array
    {
        $recaptchaSecret = config('services.recaptcha.secret_key');

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $token,
            ]);

            $data = $response->json();

            return [
                'valid' => isset($data['success']) && $data['success'] === true && ($data['score'] ?? 0) >= $threshold,
                'score' => $data['score'] ?? 0,
                'action' => $data['action'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('reCAPTCHA token validation error: ' . $e->getMessage());
            return [
                'valid' => false,
                'score' => 0,
                'action' => null,
            ];
        }
    }
}
