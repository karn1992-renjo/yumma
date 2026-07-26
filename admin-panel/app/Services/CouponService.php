<?php

namespace App\Services;

use App\Models\PromotionCouponCode;

class CouponService
{
    public function __construct(
        private readonly PromotionFinder $finder,
        private readonly CouponCodeGeneratorService $generator,
    ) {
    }

    public function find(?string $code): ?PromotionCouponCode
    {
        return $this->finder->coupon($code);
    }

    public function details(string $code): ?array
    {
        $coupon = $this->find($code);
        if (! $coupon) {
            return null;
        }

        $coupon->loadMissing('promotion');

        return [
            'id' => $coupon->id,
            'promotion_id' => $coupon->promotion_id,
            'code' => $coupon->code,
            'usage_limit' => $coupon->usage_limit,
            'used_count' => $coupon->used_count,
            'remaining_uses' => $coupon->usage_limit === null ? null : max(0, $coupon->usage_limit - $coupon->used_count),
            'is_active' => $coupon->is_active,
            'starts_at' => $coupon->starts_at,
            'ends_at' => $coupon->ends_at,
            'metadata' => $coupon->metadata,
            'promotion' => $coupon->promotion?->toPublicPayload(),
        ];
    }

    public function generateAndPersist(array $options): array
    {
        $codes = $this->generator->generate($options);

        foreach ($codes as $code) {
            PromotionCouponCode::create([
                'promotion_id' => $options['promotion_id'],
                'code' => $code,
                'user_id' => $options['user_id'] ?? null,
                'usage_limit' => $options['usage_limit'] ?? null,
                'is_active' => true,
                'metadata' => [
                    'coupon_type' => $options['coupon_type'] ?? 'bulk_generated',
                    'prefix' => $options['prefix'] ?? null,
                    'suffix' => $options['suffix'] ?? null,
                ],
            ]);
        }

        return $codes;
    }
}
