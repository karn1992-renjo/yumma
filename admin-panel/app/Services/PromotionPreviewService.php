<?php

namespace App\Services;

class PromotionPreviewService
{
    public function __construct(private readonly PromotionEngineService $engine)
    {
    }

    public function preview(array $context): array
    {
        $result = $this->engine->calculate(array_merge($context, [
            'platform' => $context['platform'] ?? 'admin_preview',
        ]));

        return array_merge($result, [
            'preview' => true,
            'simulated_at' => now()->toIso8601String(),
        ]);
    }
}
