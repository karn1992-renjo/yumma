<?php

namespace App\Services;

class PromotionAnalytics
{
    public function __construct(private readonly PromotionAnalyticsService $analytics)
    {
    }

    public function summary(array $filters = []): array
    {
        return $this->analytics->summary($filters);
    }
}
