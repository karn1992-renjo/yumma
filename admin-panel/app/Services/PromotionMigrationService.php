<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\PromoCode;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use Illuminate\Support\Collection;

class PromotionMigrationService
{
    public function migrateLegacyPromos(): int
    {
        $count = 0;

        PromoCode::query()->orderBy('id')->chunkById(200, function (Collection $promos) use (&$count) {
            foreach ($promos as $promo) {
                $promotion = Promotion::updateOrCreate(
                    [
                        'migrated_from_type' => 'promo_code',
                        'migrated_from_id' => $promo->id,
                    ],
                    [
                        'owner_type' => $promo->restaurant_id ? 'restaurant' : 'admin',
                        'owner_id' => $promo->restaurant_id,
                        'restaurant_id' => $promo->restaurant_id,
                        'title' => ($promo->title ?? null) ?: $promo->code,
                        'description' => $promo->description,
                        'promotion_type' => ($promo->discount_type ?? 'percentage') === 'percentage' ? 'percentage_discount' : 'flat_discount',
                        'application_mode' => 'coupon',
                        'status' => $promo->is_active ? 'active' : 'paused',
                        'priority' => 100,
                        'image_path' => $promo->promo_image ?? null,
                        'starts_at' => $promo->start_date ?? null,
                        'ends_at' => $promo->end_date ?? null,
                        'conditions' => [
                            'min_order_amount' => $promo->min_order_amount ?? null,
                            'audience_type' => $promo->audience_type ?? 'all',
                        ],
                        'rewards' => [
                            'type' => ($promo->discount_type ?? 'percentage') === 'percentage' ? 'percentage' : 'flat',
                            'value' => (float) ($promo->discount_value ?? 0),
                            'max_discount' => $promo->max_discount_amount !== null ? (float) $promo->max_discount_amount : null,
                        ],
                        'targets' => [
                            'restaurant_ids' => $promo->restaurant_id ? [(int) $promo->restaurant_id] : [],
                        ],
                        'total_usage_limit' => $promo->usage_limit ?? null,
                        'used_count' => (int) ($promo->used_count ?? 0),
                    ]
                );

                PromotionCouponCode::updateOrCreate(
                    ['code' => $promo->code],
                    [
                        'promotion_id' => $promotion->id,
                        'user_id' => $promo->assigned_to ?? null,
                        'usage_limit' => $promo->usage_limit ?? null,
                        'used_count' => (int) ($promo->used_count ?? 0),
                        'is_active' => (bool) ($promo->is_active ?? true),
                        'starts_at' => $promo->start_date ?? null,
                        'ends_at' => $promo->end_date ?? null,
                        'metadata' => [
                            'legacy_coupon_type' => $promo->coupon_type ?? null,
                        ],
                    ]
                );

                $count++;
            }
        });

        return $count;
    }

    public function syncCampaigns(): int
    {
        return Campaign::query()->get()->each->syncPromotionEngine()->count();
    }
}
