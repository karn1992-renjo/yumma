<?php

namespace App\Services\Ai;

class AiSimulationService
{
    public function simulate(string $tool, array $params, array $context = []): array
    {
        return [
            'success' => true,
            'simulated' => true,
            'tool' => $tool,
            'parameters' => $params,
            'expected_effect' => $context['expected_effect'] ?? 'No production state changed.',
            'simulated_at' => now()->toIso8601String(),
        ];
    }
}
