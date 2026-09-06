<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use Carbon\Carbon;

class AiCostAwareRouter
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly GeminiProvider $gemini,
        private readonly OpenAiProvider $openAi,
    ) {
    }

    public function ask(string $prompt, array $context = [], array $options = []): array
    {
        // The kill switch stops automatic action *execution* (enforced independently
        // in AiPolicyEngine for every action tool) -- it intentionally does not stop
        // analysis, monitoring, or chat from consulting the AI provider, per the
        // "analysis ON, recommendations ON, execution OFF" kill-switch definition.
        if (! $this->settings->bool('ai_enabled')) {
            return ['success' => false, 'error' => 'AI is disabled in settings.'];
        }

        $providers = array_values(array_unique([
            $options['provider'] ?? $this->settings->get('ai_provider', 'gemini'),
            $this->settings->get('ai_fallback_provider', 'openai'),
        ]));

        $errors = [];
        foreach ($providers as $providerKey) {
            $provider = $providerKey === 'openai' ? $this->openAi : $this->gemini;
            $started = microtime(true);
            $result = $provider->complete($prompt, $context, $options);
            $durationMs = (int) ((microtime(true) - $started) * 1000);

            $this->logUsage($provider->key(), $result, $durationMs);

            if ($result['success'] ?? false) {
                return $result + ['duration_ms' => $durationMs];
            }

            $errors[$provider->key()] = $result['error'] ?? 'Unknown provider error.';
        }

        $detail = collect($errors)->map(fn ($message, $providerKey) => "{$providerKey}: {$message}")->implode(' | ');

        return [
            'success' => false,
            'error' => $detail !== '' ? "All AI providers failed \u{2014} {$detail}" : 'All AI providers failed.',
            'errors' => $errors,
        ];
    }

    public function health(): array
    {
        return [
            'primary' => $this->settings->get('ai_provider', 'gemini'),
            'fallback' => $this->settings->get('ai_fallback_provider', 'openai'),
            'providers' => [
                $this->gemini->health(),
                $this->openAi->health(),
            ],
            'today_cost_usd' => (float) AiUsageLog::whereDate('created_at', today())->sum('estimated_cost'),
            'daily_budget_usd' => (float) $this->settings->get('ai_daily_budget_usd', 5),
        ];
    }

    /**
     * Live connectivity check -- actually calls each provider's API rather
     * than just reporting whether a key is stored. Only invoked on demand
     * (a "Test connection" button) to respect the cost-optimization
     * principle of not calling AI APIs on every dashboard page load.
     */
    public function testAll(): array
    {
        return [
            $this->gemini->testConnection(),
            $this->openAi->testConnection(),
        ];
    }

    private function logUsage(string $provider, array $result, int $durationMs): void
    {
        $usage = $result['usage'] ?? [];
        $inputTokens = (int) ($usage['prompt_tokens'] ?? $usage['promptTokenCount'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? $usage['candidatesTokenCount'] ?? 0);

        AiUsageLog::create([
            'provider' => $provider,
            'model' => $result['model'] ?? 'unknown',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $this->estimateCost($provider, $inputTokens, $outputTokens),
            'latency_ms' => $durationMs,
            'status' => ($result['success'] ?? false) ? 'success' : 'failed',
            'error' => $result['error'] ?? null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function estimateCost(string $provider, int $inputTokens, int $outputTokens): float
    {
        if ($provider === 'openai') {
            return round(($inputTokens * 0.00000015) + ($outputTokens * 0.0000006), 6);
        }

        return 0.0;
    }
}
