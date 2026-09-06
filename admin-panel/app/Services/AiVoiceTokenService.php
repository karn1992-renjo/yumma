<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AiVoiceTokenService
{
    public function issue(User $user, int $ttlSeconds = 900): string
    {
        $payload = [
            'sub' => (int) $user->id,
            'exp' => now()->addSeconds($ttlSeconds)->timestamp,
            'nonce' => Str::random(16),
        ];

        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedPayload, $this->secret(), true);

        return $encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    public function resolveUser(?string $token): ?User
    {
        if (! $token || ! str_contains($token, '.')) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret(), true));

        if (! hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (! is_array($decoded) || ($decoded['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return User::find((int) ($decoded['sub'] ?? 0));
    }

    private function secret(): string
    {
        $secret = (string) config('services.ai.secret');
        if ($secret === '') {
            throw new \RuntimeException('AI_SERVICE_SECRET is not configured.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4)) ?: '';
    }
}