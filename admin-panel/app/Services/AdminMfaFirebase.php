<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminMfaFirebase
{
    public function available(): bool
    {
        return filter_var(AppSetting::getValue('firebase_enabled', '0'), FILTER_VALIDATE_BOOLEAN)
            && $this->apiKeys() !== []
            && $this->projectId() !== '';
    }

    public function clientConfig(): array
    {
        $projectId = $this->projectId();

        return array_filter([
            'apiKey' => $this->apiKeys()[0] ?? null,
            'authDomain' => trim((string) AppSetting::getValue('firebase_auth_domain', '')) ?: ($projectId ? $projectId . '.firebaseapp.com' : null),
            'projectId' => $projectId ?: null,
            'storageBucket' => trim((string) AppSetting::getValue('firebase_storage_bucket', '')) ?: null,
            'messagingSenderId' => trim((string) AppSetting::getValue('firebase_messaging_sender_id', '')) ?: null,
            'appId' => trim((string) AppSetting::getValue('firebase_app_id', '')) ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function verify(string $idToken): array
    {
        if (! $this->available()) {
            $this->fail('Firebase phone verification is not configured.');
        }
        $reference = (string) Str::uuid();
        $claims = $this->claims($idToken);
        if (($claims['firebase']['sign_in_provider'] ?? null) !== 'phone' || ! is_numeric($claims['auth_time'] ?? null)) {
            Log::warning('Admin MFA Firebase token rejected', ['reference' => $reference, 'reason' => 'not_phone_auth']);
            $this->fail("Firebase did not provide a phone-auth credential. Reference: {$reference}");
        }

        foreach ($this->apiKeys() as $apiKey) {
            try {
                $response = Http::withOptions(['force_ip_resolve' => 'v4'])
                    ->connectTimeout(3)->timeout(7)
                    ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey), [
                        'idToken' => $idToken,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Admin MFA Firebase lookup failed', [
                    'reference' => $reference,
                    'reason' => 'transport_error',
                    'exception' => class_basename($e),
                ]);

                continue;
            }
            $user = $response->json('users.0');
            $phone = is_array($user) ? trim((string) ($user['phoneNumber'] ?? '')) : '';
            $uid = is_array($user) ? trim((string) ($user['localId'] ?? '')) : '';
            if ($response->successful() && $phone !== '' && $uid !== '') {
                return ['phone' => $phone, 'uid' => $uid, 'auth_time' => (int) $claims['auth_time']];
            }
            Log::warning('Admin MFA Firebase token rejected', [
                'reference' => $reference,
                'reason' => 'identity_lookup_rejected',
                'status' => $response->status(),
                'firebase_code' => $this->safeCode($response->json('error.message')),
            ]);
        }

        $this->fail("Firebase could not verify this phone session. Reference: {$reference}");
    }

    private function apiKeys(): array
    {
        $keys = [
            trim((string) AppSetting::getValue('firebase_api_key', '')),
            trim((string) config('services.firebase_client.api_key', '')),
        ];

        return array_values(array_unique(array_filter($keys, fn ($key) => $key !== '' && ! str_starts_with($key, 'PLACE_'))));
    }

    private function projectId(): string
    {
        return trim((string) AppSetting::getValue('firebase_project_id', ''))
            ?: trim((string) config('services.firebase_client.project_id', ''));
    }

    private function claims(string $token): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            return [];
        }
        $payload = strtr($segments[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $claims = json_decode((string) base64_decode($payload, true), true);

        return is_array($claims) ? $claims : [];
    }

    private function safeCode(mixed $code): ?string
    {
        if (! is_scalar($code)) {
            return null;
        }

        return preg_replace('/[^A-Za-z0-9_.-]/', '', substr((string) $code, 0, 64)) ?: null;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['firebase' => $message]);
    }
}
