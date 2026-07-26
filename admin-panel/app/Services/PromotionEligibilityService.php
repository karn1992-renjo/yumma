<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\UserReferral;

class PromotionEligibilityService
{
    public function checkViewer(Promotion $promotion, array $context, ?PromotionCouponCode $coupon = null): array
    {
        if ($promotion->status !== Promotion::STATUS_ACTIVE) {
            return $this->invalid('Promotion is not active', 'inactive');
        }

        if ($promotion->starts_at && $promotion->starts_at->isFuture()) {
            return $this->invalid('Promotion has not started yet', 'not_started');
        }

        if ($promotion->ends_at && $promotion->ends_at->isPast()) {
            return $this->invalid('Promotion has expired', 'expired');
        }

        if (! $this->matchesSchedule($promotion, $context)) {
            return $this->invalid('Promotion is not active for this time window', 'schedule_not_matched');
        }

        if ($promotion->isCouponBased()) {
            if (! $coupon) {
                return $this->invalid('Coupon code is required', 'coupon_required');
            }

            if (! $coupon->isUsableBy($context['user_id'] ?? null)) {
                return $this->invalid('Coupon is invalid or no longer usable', 'coupon_unusable');
            }
        }

        $restaurantId = (int) ($context['restaurant_id'] ?? 0);
        if (! $this->matchesRestaurant($promotion, $restaurantId)) {
            return $this->invalid('Promotion is not available for this restaurant', 'restaurant_not_matched');
        }

        $targets = $promotion->targets ?? [];
        $customerIds = array_map('intval', (array) data_get($targets, 'customer_ids', []));
        if ($customerIds && ! in_array((int) ($context['user_id'] ?? 0), $customerIds, true)) {
            return $this->invalid('Promotion is not available for this customer', 'customer_not_matched');
        }

        $customerTags = array_filter(array_map('strtolower', (array) ($context['customer_tags'] ?? [])));
        $requiredTags = array_filter(array_map('strtolower', (array) data_get($targets, 'customer_tags', [])));
        if ($requiredTags && ! array_intersect($requiredTags, $customerTags)) {
            return $this->invalid('Promotion is not available for this customer segment', 'customer_tag_not_matched');
        }

        $userGroups = array_filter(array_map('strtolower', (array) data_get($targets, 'user_groups', [])));
        $userGroup = strtolower((string) ($context['user_group'] ?? ''));
        if ($userGroups && ! in_array($userGroup, $userGroups, true)) {
            return $this->invalid('Promotion is not available for this user group', 'user_group_not_matched');
        }

        $customerTiers = array_filter(array_map('strtolower', (array) data_get($targets, 'customer_tiers', [])));
        $customerTier = strtolower((string) ($context['customer_tier'] ?? ''));
        if ($customerTiers && ! in_array($customerTier, $customerTiers, true)) {
            return $this->invalid('Promotion is not available for this customer tier', 'customer_tier_not_matched');
        }

        $conditions = $promotion->conditions ?? [];
        $audienceType = data_get($conditions, 'audience_type', data_get($targets, 'audience_type', 'all'));
        if (! $this->matchesAudience((string) $audienceType, $context)) {
            return $this->invalid('Promotion is not eligible for this account', 'audience_not_matched');
        }

        if ($this->isReferralBonus($promotion) && ! $this->hasReferralAttribution($context)) {
            return $this->invalid('Referral bonus is not available for this account', 'referral_not_matched');
        }

        $perUserLimit = $promotion->per_user_usage_limit;
        $userId = $context['user_id'] ?? null;
        if ($perUserLimit !== null && $userId) {
            $usedByUser = $promotion->usages()->where('user_id', $userId)->count();
            if ($usedByUser >= $perUserLimit) {
                return $this->invalid('Promotion usage limit exceeded for this account', 'per_user_limit_exceeded');
            }
        }

        if ($userId && ! $this->matchesRollingUsageLimits($promotion, (int) $userId)) {
            return $this->invalid('Promotion usage limit exceeded for this period', 'rolling_usage_limit_exceeded');
        }

        return ['eligible' => true, 'reason' => null, 'reason_code' => null];
    }

    public function check(Promotion $promotion, array $context, ?PromotionCouponCode $coupon = null): array
    {
        if ($promotion->status !== Promotion::STATUS_ACTIVE) {
            return $this->invalid('Promotion is not active', 'inactive');
        }

        if ($promotion->starts_at && $promotion->starts_at->isFuture()) {
            return $this->invalid('Promotion has not started yet', 'not_started');
        }

        if ($promotion->ends_at && $promotion->ends_at->isPast()) {
            return $this->invalid('Promotion has expired', 'expired');
        }

        if (! $this->matchesSchedule($promotion, $context)) {
            return $this->invalid('Promotion is not active for this time window', 'schedule_not_matched');
        }

        if ($promotion->total_usage_limit !== null && $promotion->used_count >= $promotion->total_usage_limit) {
            return $this->invalid('Promotion usage limit exceeded', 'usage_limit_exceeded');
        }

        if ($promotion->isCouponBased()) {
            if (! $coupon) {
                return $this->invalid('Coupon code is required', 'coupon_required');
            }

            if (! $coupon->isUsableBy($context['user_id'] ?? null)) {
                return $this->invalid('Coupon is invalid or no longer usable', 'coupon_unusable');
            }
        }

        $restaurantId = (int) ($context['restaurant_id'] ?? 0);
        if (! $this->matchesRestaurant($promotion, $restaurantId)) {
            return $this->invalid('Promotion is not available for this restaurant', 'restaurant_not_matched');
        }

        $targets = $promotion->targets ?? [];
        $branchIds = array_map('intval', (array) data_get($targets, 'branch_ids', []));
        if ($branchIds && ! in_array((int) ($context['branch_id'] ?? 0), $branchIds, true)) {
            return $this->invalid('Promotion is not available for this branch', 'branch_not_matched');
        }

        $zoneIds = array_map('intval', (array) data_get($targets, 'zone_ids', []));
        if ($zoneIds && ! in_array((int) ($context['zone_id'] ?? 0), $zoneIds, true)) {
            return $this->invalid('Promotion is not available for this zone', 'zone_not_matched');
        }

        foreach (['city', 'state', 'country', 'pincode'] as $locationKey) {
            $allowed = array_filter(array_map(
                fn ($value) => strtolower(trim((string) $value)),
                (array) data_get($targets, "{$locationKey}s", data_get($targets, $locationKey, []))
            ));
            $current = strtolower(trim((string) ($context[$locationKey] ?? '')));

            if ($allowed && ($current === '' || ! in_array($current, $allowed, true))) {
                return $this->invalid('Promotion is not available for this location', 'location_not_matched');
            }
        }

        $customerIds = array_map('intval', (array) data_get($targets, 'customer_ids', []));
        if ($customerIds && ! in_array((int) ($context['user_id'] ?? 0), $customerIds, true)) {
            return $this->invalid('Promotion is not available for this customer', 'customer_not_matched');
        }

        $customerTags = array_filter(array_map('strtolower', (array) ($context['customer_tags'] ?? [])));
        $requiredTags = array_filter(array_map('strtolower', (array) data_get($targets, 'customer_tags', [])));
        if ($requiredTags && ! array_intersect($requiredTags, $customerTags)) {
            return $this->invalid('Promotion is not available for this customer segment', 'customer_tag_not_matched');
        }

        $userGroups = array_filter(array_map('strtolower', (array) data_get($targets, 'user_groups', [])));
        $userGroup = strtolower((string) ($context['user_group'] ?? ''));
        if ($userGroups && ! in_array($userGroup, $userGroups, true)) {
            return $this->invalid('Promotion is not available for this user group', 'user_group_not_matched');
        }

        $customerTiers = array_filter(array_map('strtolower', (array) data_get($targets, 'customer_tiers', [])));
        $customerTier = strtolower((string) ($context['customer_tier'] ?? ''));
        if ($customerTiers && ! in_array($customerTier, $customerTiers, true)) {
            return $this->invalid('Promotion is not available for this customer tier', 'customer_tier_not_matched');
        }

        $orderType = strtolower((string) ($context['order_type'] ?? 'delivery'));
        $allowedOrderTypes = array_filter((array) data_get($targets, 'order_types', []));
        if ($allowedOrderTypes && ! in_array($orderType, array_map('strtolower', $allowedOrderTypes), true)) {
            return $this->invalid('Promotion is not available for this order type', 'order_type_not_matched');
        }

        $platform = strtolower((string) ($context['platform'] ?? 'api'));
        $allowedPlatforms = array_filter((array) data_get($targets, 'platforms', []));
        if ($allowedPlatforms && ! in_array($platform, array_map('strtolower', $allowedPlatforms), true)) {
            return $this->invalid('Promotion is not available on this platform', 'platform_not_matched');
        }

        $device = strtolower((string) ($context['device'] ?? ''));
        $allowedDevices = array_filter((array) data_get($targets, 'devices', []));
        if ($allowedDevices && ! in_array($device, array_map('strtolower', $allowedDevices), true)) {
            return $this->invalid('Promotion is not available on this device', 'device_not_matched');
        }

        $items = collect($context['items'] ?? []);
        if (! $this->cartContainsRequiredTargets($targets, $items)) {
            return $this->invalid('Promotion requires eligible cart items', 'targets_not_matched');
        }

        $conditions = $promotion->conditions ?? [];
        $reward = $promotion->rewards ?? [];
        $comboGroups = (array) data_get($reward, 'combo_groups', []);
        if ($comboGroups !== [] && ! $this->cartMatchesAnyComboGroup($comboGroups, $items)) {
            return $this->invalid('Promotion requires a complete combo group in the cart', 'combo_group_not_matched');
        }

        if ((bool) data_get($conditions, 'requires_all_target_items', false) && ! $this->cartContainsAllTargetItems($targets, $items)) {
            return $this->invalid('Promotion requires all combo items in the cart', 'all_targets_not_matched');
        }

        $subtotal = (float) ($context['subtotal'] ?? 0);
        $minOrder = data_get($conditions, 'min_order_amount');
        if ($minOrder !== null && $subtotal < (float) $minOrder) {
            return $this->invalid('Minimum order amount of ' . $this->money((float) $minOrder) . ' required', 'min_order_not_met');
        }

        $maxOrder = data_get($conditions, 'max_order_amount');
        if ($maxOrder !== null && $subtotal > (float) $maxOrder) {
            return $this->invalid('Promotion is not valid for this order amount', 'max_order_exceeded');
        }

        $paymentMethods = array_filter((array) data_get($conditions, 'payment_methods', []));
        if ($paymentMethods) {
            $paymentMethod = strtolower((string) ($context['payment_method'] ?? ''));
            if (! in_array($paymentMethod, array_map('strtolower', $paymentMethods), true)) {
                return $this->invalid('Promotion is not available for this payment method', 'payment_method_not_matched');
            }
        }

        $itemCount = $items->count();
        $quantity = $items->sum(fn ($item) => (int) ($item['quantity'] ?? 1));

        $minItems = data_get($conditions, 'min_item_count');
        if ($minItems !== null && $itemCount < (int) $minItems) {
            return $this->invalid('Promotion requires more items in the cart', 'min_item_count_not_met');
        }

        $minQuantity = data_get($conditions, 'min_quantity');
        if ($minQuantity !== null && $quantity < (int) $minQuantity) {
            return $this->invalid('Promotion requires more quantity in the cart', 'min_quantity_not_met');
        }

        $maxQuantity = data_get($conditions, 'max_quantity');
        if ($maxQuantity !== null && $quantity > (int) $maxQuantity) {
            return $this->invalid('Promotion is not valid for this quantity', 'max_quantity_exceeded');
        }

        if (! $this->matchesCartConditions($conditions, $items)) {
            return $this->invalid('Promotion is not valid for the selected cart items', 'cart_conditions_not_matched');
        }

        foreach ([
            'delivery_fee' => 'delivery fee',
            'packaging_fee' => 'packaging fee',
            'tax' => 'tax',
            'distance_km' => 'delivery distance',
            'weight' => 'order weight',
        ] as $key => $label) {
            $min = data_get($conditions, "min_{$key}");
            $max = data_get($conditions, "max_{$key}");
            $value = $context[$key] ?? null;

            if ($min !== null && (float) $value < (float) $min) {
                return $this->invalid('Promotion requires a higher ' . $label, "min_{$key}_not_met");
            }

            if ($max !== null && (float) $value > (float) $max) {
                return $this->invalid('Promotion is not valid for this ' . $label, "max_{$key}_exceeded");
            }
        }

        if ((bool) data_get($conditions, 'coupon_required', false) && empty($context['coupon_code'])) {
            return $this->invalid('Coupon code is required', 'coupon_required');
        }

        $audienceType = data_get($conditions, 'audience_type', data_get($targets, 'audience_type', 'all'));
        if (! $this->matchesAudience((string) $audienceType, $context)) {
            return $this->invalid('Promotion is not eligible for this account', 'audience_not_matched');
        }

        if ($this->isReferralBonus($promotion) && ! $this->hasReferralAttribution($context)) {
            return $this->invalid('Referral bonus is not available for this account', 'referral_not_matched');
        }

        $perUserLimit = $promotion->per_user_usage_limit;
        $userId = $context['user_id'] ?? null;
        if ($perUserLimit !== null && $userId) {
            $usedByUser = $promotion->usages()->where('user_id', $userId)->count();
            if ($usedByUser >= $perUserLimit) {
                return $this->invalid('Promotion usage limit exceeded for this account', 'per_user_limit_exceeded');
            }
        }

        if ($userId && ! $this->matchesRollingUsageLimits($promotion, (int) $userId)) {
            return $this->invalid('Promotion usage limit exceeded for this period', 'rolling_usage_limit_exceeded');
        }

        return ['eligible' => true, 'reason' => null];
    }

    private function cartContainsRequiredTargets(array $targets, $items): bool
    {
        if ($items->isEmpty()) {
            return true;
        }

        $itemIds = array_map('intval', (array) data_get($targets, 'item_ids', data_get($targets, 'menu_item_ids', [])));
        $categoryIds = array_map('intval', (array) data_get($targets, 'category_ids', []));
        $cuisineIds = array_map('intval', (array) data_get($targets, 'cuisine_ids', []));
        $menuTags = array_filter(array_map('strtolower', (array) data_get($targets, 'menu_tags', [])));

        if (! $itemIds && ! $categoryIds && ! $cuisineIds && ! $menuTags) {
            return true;
        }

        return $items->contains(function (array $item) use ($itemIds, $categoryIds, $cuisineIds, $menuTags) {
            if ($itemIds && in_array((int) ($item['id'] ?? $item['menu_item_id'] ?? 0), $itemIds, true)) {
                return true;
            }

            if ($categoryIds && in_array((int) ($item['category_id'] ?? 0), $categoryIds, true)) {
                return true;
            }

            if ($cuisineIds && in_array((int) ($item['cuisine_id'] ?? 0), $cuisineIds, true)) {
                return true;
            }

            $itemTags = array_filter(array_map('strtolower', (array) ($item['tags'] ?? [])));

            return $menuTags && (bool) array_intersect($menuTags, $itemTags);
        });
    }

    private function cartContainsAllTargetItems(array $targets, $items): bool
    {
        $itemIds = array_values(array_filter(array_map(
            'intval',
            (array) data_get($targets, 'item_ids', data_get($targets, 'menu_item_ids', []))
        )));

        if ($itemIds === []) {
            return true;
        }

        $cartItemIds = $items
            ->map(fn (array $item) => (int) ($item['id'] ?? $item['menu_item_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return collect($itemIds)->every(fn (int $itemId) => in_array($itemId, $cartItemIds, true));
    }

    private function cartMatchesAnyComboGroup(array $comboGroups, $items): bool
    {
        $cartItemIds = $items
            ->map(fn (array $item) => (int) ($item['id'] ?? $item['menu_item_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($cartItemIds === []) {
            return false;
        }

        return collect($comboGroups)
            ->filter(fn ($group) => is_array($group))
            ->contains(function (array $group) use ($cartItemIds) {
                $groupItemIds = collect($group['item_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                return $groupItemIds->count() >= 2
                    && $groupItemIds->every(fn (int $itemId) => in_array($itemId, $cartItemIds, true));
            });
    }

    private function matchesCartConditions(array $conditions, $items): bool
    {
        $containsItemIds = array_map('intval', (array) data_get($conditions, 'contains_item_ids', []));
        if ($containsItemIds && ! $items->contains(fn (array $item) => in_array((int) ($item['id'] ?? $item['menu_item_id'] ?? 0), $containsItemIds, true))) {
            return false;
        }

        $containsCategoryIds = array_map('intval', (array) data_get($conditions, 'contains_category_ids', []));
        if ($containsCategoryIds && ! $items->contains(fn (array $item) => in_array((int) ($item['category_id'] ?? 0), $containsCategoryIds, true))) {
            return false;
        }

        $excludeItemIds = array_map('intval', (array) data_get($conditions, 'exclude_item_ids', []));
        if ($excludeItemIds && $items->contains(fn (array $item) => in_array((int) ($item['id'] ?? $item['menu_item_id'] ?? 0), $excludeItemIds, true))) {
            return false;
        }

        $excludeCategoryIds = array_map('intval', (array) data_get($conditions, 'exclude_category_ids', []));
        if ($excludeCategoryIds && $items->contains(fn (array $item) => in_array((int) ($item['category_id'] ?? 0), $excludeCategoryIds, true))) {
            return false;
        }

        return true;
    }

    private function matchesSchedule(Promotion $promotion, array $context): bool
    {
        $now = now();
        $schedule = $promotion->schedule ?? [];

        $weekdays = array_map('intval', (array) data_get($schedule, 'weekdays', []));
        if ($weekdays && ! in_array((int) $now->dayOfWeekIso, $weekdays, true)) {
            return false;
        }

        if ((bool) data_get($schedule, 'weekends_only', false) && ! $now->isWeekend()) {
            return false;
        }

        $startTime = $promotion->starts_time ?: data_get($schedule, 'start_time');
        $endTime = $promotion->ends_time ?: data_get($schedule, 'end_time');
        if ($startTime && $endTime) {
            $current = $now->format('H:i:s');
            $start = strlen((string) $startTime) === 5 ? "{$startTime}:00" : (string) $startTime;
            $end = strlen((string) $endTime) === 5 ? "{$endTime}:00" : (string) $endTime;

            if ($start <= $end) {
                return $current >= $start && $current <= $end;
            }

            return $current >= $start || $current <= $end;
        }

        return true;
    }

    private function matchesRestaurant(Promotion $promotion, int $restaurantId): bool
    {
        if ($promotion->restaurant_id !== null) {
            return (int) $promotion->restaurant_id === $restaurantId;
        }

        $restaurantIds = array_map('intval', (array) data_get($promotion->targets ?? [], 'restaurant_ids', []));

        return $restaurantIds === [] || in_array($restaurantId, $restaurantIds, true);
    }

    private function matchesAudience(string $audienceType, array $context): bool
    {
        $audienceType = strtolower($audienceType);
        if ($audienceType === '' || $audienceType === 'all') {
            return true;
        }

        $userId = $context['user_id'] ?? null;
        if (! $userId) {
            return false;
        }

        $orderCount = Order::where('customer_id', $userId)->count();

        return match ($audienceType) {
            'new_customer', 'first_order' => $orderCount === 0,
            'returning_customer' => $orderCount > 0,
            'nth_order' => $orderCount + 1 === (int) ($context['nth_order'] ?? 0),
            default => true,
        };
    }

    private function isReferralBonus(Promotion $promotion): bool
    {
        $rewardType = strtolower((string) data_get($promotion->rewards ?? [], 'type', $promotion->promotion_type));

        return $rewardType === 'referral_bonus';
    }

    private function hasReferralAttribution(array $context): bool
    {
        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        return UserReferral::query()
            ->where('referred_user_id', $userId)
            ->whereIn('status', ['registered', 'qualified', 'credited'])
            ->exists();
    }

    private function matchesRollingUsageLimits(Promotion $promotion, int $userId): bool
    {
        $conditions = $promotion->conditions ?? [];
        $query = $promotion->usages()->where('user_id', $userId);

        $perDay = data_get($conditions, 'per_user_daily_limit');
        if ($perDay !== null && (clone $query)->whereDate('created_at', today())->count() >= (int) $perDay) {
            return false;
        }

        $perWeek = data_get($conditions, 'per_user_weekly_limit');
        if ($perWeek !== null && (clone $query)->where('created_at', '>=', now()->startOfWeek())->count() >= (int) $perWeek) {
            return false;
        }

        $perMonth = data_get($conditions, 'per_user_monthly_limit');
        if ($perMonth !== null && (clone $query)->where('created_at', '>=', now()->startOfMonth())->count() >= (int) $perMonth) {
            return false;
        }

        return true;
    }

    private function invalid(string $reason, ?string $reasonCode = null): array
    {
        return [
            'eligible' => false,
            'reason' => $reason,
            'reason_code' => $reasonCode,
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
