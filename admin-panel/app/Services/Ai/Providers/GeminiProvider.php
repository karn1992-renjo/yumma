<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AiProviderInterface
{
    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    public function key(): string
    {
        return 'gemini';
    }

    public function complete(string $prompt, array $context = [], array $options = []): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            return ['success' => false, 'error' => 'Gemini API key is not configured.'];
        }

        $model = $options['model'] ?? $this->settings->get('gemini_model', 'gemini-1.5-flash');
        $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->timeout(45)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => $prompt . "\n\nContext JSON:\n" . json_encode($context, JSON_PRETTY_PRINT),
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]
        );

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message', $response->body()) ?: ('HTTP '.$response->status());

            return ['success' => false, 'error' => $message, 'status' => $response->status()];
        }

        $payload = $response->json();
        $text = data_get($payload, 'candidates.0.content.parts.0.text', '{}');

        return [
            'success' => true,
            'provider' => $this->key(),
            'model' => $model,
            'content' => $text,
            'usage' => $payload['usageMetadata'] ?? [],
            'raw' => $payload,
        ];
    }

    public function health(): array
    {
        return ['provider' => $this->key(), 'configured' => (bool) $this->apiKey()];
    }

    public function testConnection(): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            return ['provider' => $this->key(), 'success' => false, 'status' => 'not_configured', 'message' => 'No Gemini API key is configured.'];
        }

        $started = microtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(10)
                ->get('https://generativelanguage.googleapis.com/v1beta/models');
        } catch (\Throwable $exception) {
            return [
                'provider' => $this->key(),
                'success' => false,
                'status' => 'network_error',
                'message' => $exception->getMessage(),
                'latency_ms' => (int) ((microtime(true) - $started) * 1000),
            ];
        }

        $latencyMs = (int) ((microtime(true) - $started) * 1000);

        if ($response->successful()) {
            return [
                'provider' => $this->key(),
                'success' => true,
                'status' => 'connected',
                'message' => 'Connected. '.count($response->json('models', [])).' models visible to this key.',
                'latency_ms' => $latencyMs,
            ];
        }

        return [
            'provider' => $this->key(),
            'success' => false,
            'status' => 'http_'.$response->status(),
            'message' => data_get($response->json(), 'error.message', $response->body()) ?: ('HTTP '.$response->status()),
            'latency_ms' => $latencyMs,
        ];
    }

    private function apiKey(): ?string
    {
        return $this->settings->get('gemini_api_key') ?: config('services.gemini.key') ?: env('GEMINI_API_KEY');
    }
}


