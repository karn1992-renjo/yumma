<?php

namespace App\Services;

use App\Models\PromotionLog;

class PromotionLogger
{
    public function log(string $eventType, array $context, array $result, ?int $promotionId = null, ?string $couponCode = null): void
    {
        $startedAt = $context['_started_at'] ?? null;
        $calculationMs = $startedAt ? round((microtime(true) - (float) $startedAt) * 1000, 2) : null;

        PromotionLog::create([
            'promotion_id' => $promotionId,
            'user_id' => $context['user_id'] ?? null,
            'restaurant_id' => $context['restaurant_id'] ?? null,
            'event_type' => $eventType,
            'coupon_code' => $couponCode,
            'context' => collect($context)->except('_started_at')->all(),
            'result' => [
                'discount' => $result['discount'] ?? 0,
                'final_total' => $result['final_total'] ?? 0,
                'applied_count' => count($result['applied_promotions'] ?? []),
                'eligible_count' => count($result['eligible_promotions'] ?? []),
                'rejected_count' => count($result['invalid_reasons'] ?? []),
                'invalid_reasons' => $result['invalid_reasons'] ?? [],
                'applied_promotion_ids' => collect($result['applied_promotions'] ?? [])
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all(),
                'discount_buckets' => collect($result['discount_lines'] ?? [])
                    ->groupBy(fn ($line) => $line['bucket'] ?? 'unknown')
                    ->map(fn ($lines) => round((float) $lines->sum('discount_amount'), 2))
                    ->all(),
                'reward_line_count' => count($result['reward_lines'] ?? []),
                'calculation_ms' => $calculationMs,
            ],
        ]);
    }
}
