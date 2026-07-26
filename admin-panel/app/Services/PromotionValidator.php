<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionCouponCode;

class PromotionValidator
{
    public function __construct(private readonly PromotionEligibilityService $eligibility)
    {
    }

    public function check(Promotion $promotion, array $context, ?PromotionCouponCode $coupon = null): array
    {
        return $this->eligibility->check($promotion, $context, $coupon);
    }

    public function checkViewer(Promotion $promotion, array $context, ?PromotionCouponCode $coupon = null): array
    {
        return $this->eligibility->checkViewer($promotion, $context, $coupon);
    }
}
