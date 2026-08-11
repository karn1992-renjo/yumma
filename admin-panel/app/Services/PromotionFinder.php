<?php

namespace App\Services;

use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PromotionFinder
{
    public function candidates(array $context, ?int $limit = null): Collection
    {
        $normalized = $this->normalizeContext($context);
        if ($normalized['coupon_code']) {
            $query = $this->candidateQuery($context);
            if ($limit !== null) {
                $query->limit($limit);
            }

            return $query->get();
        }

        $cacheKey = 'promotion_engine:active:'
            . md5(json_encode([
                'restaurant_id' => $normalized['restaurant_id'],
                'branch_id' => $normalized['branch_id'],
                'order_type' => $normalized['order_type'],
                'platform' => $normalized['platform'],
                'owner_type' => $context['owner_type'] ?? null,
                'limit' => $limit,
            ]));

        return Cache::remember($cacheKey, now()->addSeconds(60), function () use ($context, $limit) {
            $query = $this->candidateQuery($context);
            if ($limit !== null) {
                $query->limit($limit);
            }

            return $query->get();
        });
    }

    public function coupon(?string $code): ?PromotionCouponCode
    {
        $code = $this->normalizeCode($code);
        if (! $code) {
            return null;
        }

        return PromotionCouponCode::query()
            ->with('promotion')
            ->active()
            ->where('code', $code)
            ->first();
    }

    public function activeCoupons(array $context, int $limit = 100): Collection
    {
        $context = $this->normalizeContext($context);

        return PromotionCouponCode::query()
            ->with('promotion')
            ->active()
            ->where(function ($query) use ($context) {
                $query->whereNull('user_id');

                if ($context['user_id']) {
                    $query->orWhere('user_id', $context['user_id']);
                }
            })
            ->whereHas('promotion', function ($query) use ($context) {
                $query->active()
                    ->where('application_mode', 'coupon')
                    ->where(function ($builder) use ($context) {
                        $builder->whereNull('restaurant_id');

                        if ($context['restaurant_id']) {
                            $builder->orWhere('restaurant_id', $context['restaurant_id']);
                        }
                    });
            })
            ->limit($limit)
            ->get();
    }

    public function normalizeContext(array $context): array
    {
        return [
            'user_id' => $context['user_id'] ?? auth()->id(),
            'restaurant_id' => isset($context['restaurant_id']) ? (int) $context['restaurant_id'] : null,
            'branch_id' => $context['branch_id'] ?? null,
            'zone_id' => $context['zone_id'] ?? null,
            'city' => isset($context['city']) ? strtolower((string) $context['city']) : null,
            'state' => isset($context['state']) ? strtolower((string) $context['state']) : null,
            'country' => isset($context['country']) ? strtolower((string) $context['country']) : null,
            'pincode' => isset($context['pincode']) ? (string) $context['pincode'] : null,
            'subtotal' => round((float) ($context['subtotal'] ?? 0), 2),
            'delivery_fee' => round((float) ($context['delivery_fee'] ?? 0), 2),
            'platform_fee' => round((float) ($context['platform_fee'] ?? 0), 2),
            'packaging_fee' => round((float) ($context['packaging_fee'] ?? 0), 2),
            'tax' => round((float) ($context['tax'] ?? 0), 2),
            'coupon_code' => $this->normalizeCode($context['coupon_code'] ?? $context['code'] ?? null),
            'order_type' => strtolower((string) ($context['order_type'] ?? 'delivery')),
            'platform' => strtolower((string) ($context['platform'] ?? 'api')),
            'device' => strtolower((string) ($context['device'] ?? '')),
            'device_id' => strtolower((string) ($context['device_id'] ?? $context['device'] ?? '')),
            'address_hash' => strtolower((string) ($context['address_hash'] ?? $context['delivery_address_hash'] ?? '')),
            'payment_instrument_hash' => strtolower((string) ($context['payment_instrument_hash'] ?? '')),
            'items' => $context['items'] ?? [],
            'payment_method' => $context['payment_method'] ?? null,
            'nth_order' => isset($context['nth_order']) ? (int) $context['nth_order'] : null,
            'distance_km' => isset($context['distance_km']) ? (float) $context['distance_km'] : null,
            'weight' => isset($context['weight']) ? (float) $context['weight'] : null,
            'customer_tags' => (array) ($context['customer_tags'] ?? []),
            'user_group' => $context['user_group'] ?? null,
            'customer_tier' => $context['customer_tier'] ?? null,
        ];
    }

    public function normalizeCode(?string $code): ?string
    {
        $code = trim((string) $code);

        return $code === '' ? null : strtoupper($code);
    }

    private function candidateQuery(array $context)
    {
        $restaurantId = $context['restaurant_id'] ?? null;

        return Promotion::query()
            ->with('couponCodes')
            ->active()
            ->when($context['owner_type'] ?? null, fn ($query, $ownerType) => $query->where('owner_type', $ownerType))
            ->where(function ($query) use ($restaurantId) {
                $query->whereNull('restaurant_id');

                if ($restaurantId) {
                    $query->orWhere('restaurant_id', $restaurantId);
                }
            })
            ->orderBy('priority')
            ->orderByDesc('created_at');
    }
}

