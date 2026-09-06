<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\VoiceAiSession;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class VoiceAiSettingsService
{
    public const PROVIDER_GEMINI = 'gemini_live';
    public const PROVIDER_SELF_HOSTED = 'self_hosted';
    public const PROVIDER_NONE = 'none';

    public function settings(): array
    {
        $raw = AppSetting::all()->pluck('value', 'key')->toArray();

        return [
            'voice_ai_enabled' => $this->bool($raw['voice_ai_enabled'] ?? '1'),
            'voice_ai_primary_provider' => $this->provider($raw['voice_ai_primary_provider'] ?? self::PROVIDER_SELF_HOSTED),
            'voice_ai_fallback_provider' => $this->fallback($raw['voice_ai_fallback_provider'] ?? self::PROVIDER_NONE),
            'voice_ai_max_session_minutes' => $this->int($raw['voice_ai_max_session_minutes'] ?? 10, 1, 60),
            'voice_ai_idle_timeout_seconds' => $this->int($raw['voice_ai_idle_timeout_seconds'] ?? 45, 10, 300),
            'voice_ai_barge_in_enabled' => $this->bool($raw['voice_ai_barge_in_enabled'] ?? '1'),
            'voice_ai_language' => $this->string($raw['voice_ai_language'] ?? 'auto', 20),
            'voice_ai_allowance_enabled' => $this->bool($raw['voice_ai_allowance_enabled'] ?? '1'),
            'voice_ai_initial_allowance_seconds' => $this->int($raw['voice_ai_initial_allowance_seconds'] ?? 600, 0, 86400),
            'voice_ai_recharge_after_order' => $this->bool($raw['voice_ai_recharge_after_order'] ?? '1'),
            'voice_ai_recharge_seconds' => $this->int($raw['voice_ai_recharge_seconds'] ?? 600, 0, 86400),
            'voice_ai_recharge_mode' => in_array(($raw['voice_ai_recharge_mode'] ?? 'reset'), ['reset', 'add'], true) ? ($raw['voice_ai_recharge_mode'] ?? 'reset') : 'reset',
            'voice_ai_gemini_model' => $this->string($raw['voice_ai_gemini_model'] ?? 'gemini-3.1-flash-live-preview', 120),
            'voice_ai_gemini_voice' => $this->string($raw['voice_ai_gemini_voice'] ?? 'Puck', 60),
            'voice_ai_gemini_system_prompt' => $this->string($raw['voice_ai_gemini_system_prompt'] ?? $this->defaultPrompt(), 5000),
            'voice_ai_gemini_temperature' => $this->float($raw['voice_ai_gemini_temperature'] ?? 0.2, 0, 2),
            'voice_ai_gemini_tool_calling_enabled' => $this->bool($raw['voice_ai_gemini_tool_calling_enabled'] ?? '1'),
            'voice_ai_self_hosted_url' => rtrim($this->string($raw['voice_ai_self_hosted_url'] ?? config('app.url', ''), 500), '/'),
            'voice_ai_self_hosted_ws_url' => rtrim($this->string($raw['voice_ai_self_hosted_ws_url'] ?? '', 500), '/'),
            'voice_ai_self_hosted_llm_model' => $this->string($raw['voice_ai_self_hosted_llm_model'] ?? 'Qwen/Qwen3-8B-AWQ', 160),
            'voice_ai_gemini_api_key_configured' => $this->decryptSecret($raw['voice_ai_gemini_api_key_encrypted'] ?? '') !== '',
            'voice_ai_self_hosted_api_secret_configured' => $this->decryptSecret($raw['voice_ai_self_hosted_api_secret_encrypted'] ?? '') !== '',
        ];
    }

    public function publicConfig(int $remainingSeconds): array
    {
        $settings = $this->settings();
        $enabled = (bool) $settings['voice_ai_enabled'];
        $allowanceEnabled = (bool) $settings['voice_ai_allowance_enabled'];

        return [
            'enabled' => $enabled && (! $allowanceEnabled || $remainingSeconds > 0),
            'service_enabled' => $enabled,
            'allowance_enabled' => $allowanceEnabled,
            'remaining_seconds' => $remainingSeconds,
            'session_limit_seconds' => (int) $settings['voice_ai_max_session_minutes'] * 60,
            'idle_timeout_seconds' => (int) $settings['voice_ai_idle_timeout_seconds'],
            'barge_in_enabled' => (bool) $settings['voice_ai_barge_in_enabled'],
            'language' => $settings['voice_ai_language'],
            'gateway_url' => $settings['voice_ai_self_hosted_url'] ?: config('app.url'),
            'gateway_ws_url' => $settings['voice_ai_self_hosted_ws_url'],
        ];
    }

    public function internalConfig(): array
    {
        $raw = AppSetting::all()->pluck('value', 'key')->toArray();
        $settings = $this->settings();

        return $settings + [
            'voice_ai_gemini_api_key' => $this->decryptSecret($raw['voice_ai_gemini_api_key_encrypted'] ?? ''),
            'voice_ai_self_hosted_api_secret' => $this->decryptSecret($raw['voice_ai_self_hosted_api_secret_encrypted'] ?? ''),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function update(array $input): void
    {
        $values = collect($input)->except([
            '_token',
            'gemini_api_key',
            'self_hosted_api_secret',
        ])->all();

        foreach ($values as $key => $value) {
            AppSetting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : $value);
        }

        if (array_key_exists('gemini_api_key', $input) && trim((string) $input['gemini_api_key']) !== '') {
            AppSetting::setValue('voice_ai_gemini_api_key_encrypted', Crypt::encryptString(trim((string) $input['gemini_api_key'])));
        }

        if (array_key_exists('self_hosted_api_secret', $input) && trim((string) $input['self_hosted_api_secret']) !== '') {
            AppSetting::setValue('voice_ai_self_hosted_api_secret_encrypted', Crypt::encryptString(trim((string) $input['self_hosted_api_secret'])));
        }
    }

    public function health(): array
    {
        $settings = $this->settings();
        $selfHostedUrl = $settings['voice_ai_self_hosted_url'];
        $selfHosted = ['status' => 'unknown'];

        if ($selfHostedUrl !== '') {
            try {
                $response = Http::timeout(5)->acceptJson()->get($selfHostedUrl.'/voice-ai/health');
                $selfHosted = $response->ok()
                    ? array_merge(['status' => 'ok'], (array) $response->json())
                    : ['status' => 'down', 'http_status' => $response->status()];
            } catch (\Throwable $exception) {
                $selfHosted = ['status' => 'down', 'message' => $exception->getMessage()];
            }
        }

        return [
            'enabled' => (bool) $settings['voice_ai_enabled'],
            'primary_provider' => $settings['voice_ai_primary_provider'],
            'fallback_provider' => $settings['voice_ai_fallback_provider'],
            'gemini_live' => [
                'status' => $settings['voice_ai_gemini_api_key_configured'] ? 'configured' : 'misconfigured',
                'model' => $settings['voice_ai_gemini_model'],
            ],
            'self_hosted' => $selfHosted,
            'recent_sessions' => VoiceAiSession::query()->latest()->limit(10)->get(),
        ];
    }

    private function decryptSecret(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $exception) {
            return '';
        }
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function int(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function float(mixed $value, float $min, float $max): float
    {
        return max($min, min($max, (float) $value));
    }

    private function string(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    private function provider(string $value): string
    {
        return in_array($value, [self::PROVIDER_GEMINI, self::PROVIDER_SELF_HOSTED], true) ? $value : self::PROVIDER_SELF_HOSTED;
    }

    private function fallback(string $value): string
    {
        return in_array($value, [self::PROVIDER_NONE, self::PROVIDER_GEMINI, self::PROVIDER_SELF_HOSTED], true) ? $value : self::PROVIDER_NONE;
    }

    private function defaultPrompt(): string
    {
        return "You are Swado's realtime food ordering assistant. Speak briefly in English, Hindi, or Hinglish. Use tools for restaurant, menu, cart, order, address, payment, and status data. Never invent prices, stock, fees, ETA, order status, or addresses. Ask for confirmation before placing or cancelling an order.";
    }
}
