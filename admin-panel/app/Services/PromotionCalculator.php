<?php

namespace App\Services;

use App\Models\Promotion;

class PromotionCalculator
{
    public function __construct(private readonly PromotionRewardService $rewards)
    {
    }

    public function calculate(Promotion $promotion, array $context): array
    {
        return $this->rewards->calculate($promotion, $context);
    }

    public function preview(Promotion $promotion, array $context): ?array
    {
        return $this->rewards->preview($promotion, $context);
    }
}
