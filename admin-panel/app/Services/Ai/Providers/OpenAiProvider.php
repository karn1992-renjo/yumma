<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    public function key(): string
    {
        return 'openai';
    }

    public function complete(string $prompt, array $context = [], array $options = []): array
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            return ['success' => false, 'error' => 'OpenAI API key is not configured.'];
        }

        $model = $options['model'] ?? $this->settings->get('openai_model', 'gpt-4o-mini');
        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => $options['temperature'] ?? 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return compact valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt . "\n\nContext JSON:\n" . json_encode($context, JSON_PRETTY_PRINT)],
                ],
            ]);

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message', $response->body()) ?: ('HTTP '.$response->status());

            return ['success' => false, 'error' => $message, 'status' => $response->status()];
        }

        $payload = $response->json();

        return [
            'success' => true,
            'provider' => $this->key(),
            'model' => $model,
            'content' => data_get($payload, 'choices.0.message.content', '{}'),
            'usage' => $payload['usage'] ?? [],
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
            return ['provider' => $this->key(), 'success' => false, 'status' => 'not_configured', 'message' => 'No OpenAI API key is configured.'];
        }

        $started = microtime(true);

        try {
            $response = Http::withToken($apiKey)->timeout(10)->get('https://api.openai.com/v1/models');
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
                'message' => 'Connected. '.count($response->json('data', [])).' models visible to this key.',
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
        return $this->settings->get('openai_api_key') ?: config('services.openai.key') ?: env('OPENAI_API_KEY');
    }
}
