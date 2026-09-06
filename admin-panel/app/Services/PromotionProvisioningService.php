<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\User;
use App\Services\Ai\AiMarginGuardService;
use Illuminate\Support\Str;

/**
 * AI-safe promotion create/modify/stop path -- a deliberately narrow slice
 * of the full admin promotion engine (App\Http\Controllers\Admin\
 * PromotionEngineController::payload(), 130+ fields covering scratch cards,
 * combo deals, referral bonuses, item/category targeting, etc). The AI is
 * only allowed to propose three simple, well-understood promotion types --
 * a whole-order percentage discount, free delivery, or an item-scoped
 * percentage discount -- so it can't misconfigure the more exotic reward
 * mechanics that exist for human admins. AI-created promotions are tagged
 * owner_type='ai'; modify/stop are restricted to promotions the AI itself
 * created, so it can never silently alter an admin-crafted campaign.
 *
 * Every discount is checked against App\Services\Ai\AiMarginGuardService
 * before it's created -- a promotion that would give away more than the
 * real commission margin it's funded from is rejected outright, never
 * created as a draft for later cleanup.
 */
class PromotionProvisioningService
{
    private const ALLOWED_TYPES = [
        'percentage_discount' => 'festival_offer',
        'free_delivery' => 'free_delivery',
        'item_discount' => 'item_discount',
    ];

    public function __construct(private readonly AiMarginGuardService $marginGuard)
    {
    }

    public function createOffer(array $data): array
    {
        $promotionType = self::ALLOWED_TYPES[$data['promotion_type'] ?? null] ?? null;
        if (! $promotionType) {
            return ['success' => false, 'error' => 'promotion_type must be one of: '.implode(', ', array_keys(self::ALLOWED_TYPES))];
        }

        if (empty($data['title'])) {
            return ['success' => false, 'error' => 'title is required.'];
        }

        $restaurantId = ! empty($data['restaurant_id']) ? (int) $data['restaurant_id'] : null;
        $isPercentBased = in_array($promotionType, ['festival_offer', 'item_discount'], true);

        if ($isPercentBased) {
            $value = (float) ($data['reward_value'] ?? 0);
            if ($value <= 0 || $value > 100) {
                return ['success' => false, 'error' => 'reward_value (percent off) must be between 1 and 100.'];
            }
        }

        $itemIds = [];
        if ($promotionType === 'item_discount') {
            if (! $restaurantId) {
                return ['success' => false, 'error' => 'item_discount requires restaurant_id.'];
            }
            $itemIds = array_values(array_unique(array_map('intval', $data['item_ids'] ?? [])));
            if ($itemIds === []) {
                return ['success' => false, 'error' => 'item_discount requires at least one item_ids entry.'];
            }
            $validItemIds = MenuItem::where('restaurant_id', $restaurantId)->whereIn('id', $itemIds)->pluck('id')->all();
            if (count($validItemIds) !== count($itemIds)) {
                return ['success' => false, 'error' => 'One or more item_ids do not belong to restaurant_id '.$restaurantId.'.'];
            }
        }

        $audienceType = ($data['audience_type'] ?? 'all') === 'first_order' ? 'first_order' : 'all';
        $fundingType = $restaurantId ? 'restaurant' : 'platform';

        $rewards = array_filter([
            'type' => $isPercentBased ? ($promotionType === 'item_discount' ? 'item_discount' : 'percentage') : 'free_delivery',
            'value' => $isPercentBased ? (float) $data['reward_value'] : 0,
            'max_discount' => isset($data['max_discount']) ? (float) $data['max_discount'] : null,
            'item_ids' => $itemIds,
        ], fn ($value) => $value !== null && $value !== []);

        $estimatedOrderValue = $this->estimateOrderValue($restaurantId, $data);
        $marginCheck = $this->marginGuard->checkPromotion($restaurantId, $fundingType, $rewards, $estimatedOrderValue);
        if (! $marginCheck['allowed']) {
            return ['success' => false, 'error' => $marginCheck['reason'], 'margin_check' => $marginCheck];
        }

        $promotion = Promotion::create([
            'owner_type' => 'ai',
            'owner_id' => null,
            'restaurant_id' => $restaurantId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'promotion_type' => $promotionType,
            'application_mode' => 'automatic',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 100,
            'is_exclusive' => false,
            'funding_type' => $fundingType,
            'total_budget' => isset($data['total_budget']) ? (float) $data['total_budget'] : null,
            'daily_budget' => isset($data['daily_budget']) ? (float) $data['daily_budget'] : null,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'targets' => [
                'restaurant_ids' => $restaurantId ? [$restaurantId] : [],
                'item_ids' => $itemIds,
            ],
            'conditions' => array_filter([
                'min_order_amount' => isset($data['min_order_amount']) ? (float) $data['min_order_amount'] : null,
                'audience_type' => $audienceType,
            ], fn ($value) => $value !== null),
            'rewards' => $rewards,
            'stacking' => ['allow_multiple' => false, 'allow_coupon_promotion' => true, 'best_offer_only' => false],
            'fraud_rules' => ['one_per_user' => false],
            'visibility' => [],
        ]);

        return ['success' => true, 'promotion_id' => $promotion->id, 'promotion' => $promotion->fresh(), 'margin_check' => $marginCheck];
    }

    public function modifyOffer(Promotion $promotion, array $data): array
    {
        if ($promotion->owner_type !== 'ai') {
            return ['success' => false, 'error' => 'The AI can only modify promotions it created itself.'];
        }

        $updates = [];

        foreach (['title', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('ends_at', $data)) {
            $updates['ends_at'] = $data['ends_at'];
        }

        if (array_key_exists('status', $data)) {
            if (! in_array($data['status'], [Promotion::STATUS_DRAFT, Promotion::STATUS_ACTIVE, Promotion::STATUS_PAUSED], true)) {
                return ['success' => false, 'error' => "Invalid status [{$data['status']}]."];
            }
            $updates['status'] = $data['status'];
        }

        if (array_key_exists('reward_value', $data) && in_array($promotion->rewards['type'] ?? null, ['percentage', 'item_discount'], true)) {
            $value = (float) $data['reward_value'];
            if ($value <= 0 || $value > 100) {
                return ['success' => false, 'error' => 'reward_value must be between 1 and 100 for a percentage discount.'];
            }

            $estimatedOrderValue = $this->estimateOrderValue($promotion->restaurant_id, $data);
            $rewards = $promotion->rewards ?? [];
            $rewards['value'] = $value;
            $marginCheck = $this->marginGuard->checkPromotion($promotion->restaurant_id, $promotion->funding_type ?? 'platform', $rewards, $estimatedOrderValue);
            if (! $marginCheck['allowed']) {
                return ['success' => false, 'error' => $marginCheck['reason'], 'margin_check' => $marginCheck];
            }
            $updates['rewards'] = $rewards;
        }

        if (array_key_exists('min_order_amount', $data)) {
            $conditions = $promotion->conditions ?? [];
            $conditions['min_order_amount'] = (float) $data['min_order_amount'];
            $updates['conditions'] = $conditions;
        }

        if ($updates === []) {
            return ['success' => false, 'error' => 'No recognized fields to update.'];
        }

        $promotion->update($updates);

        return ['success' => true, 'promotion_id' => $promotion->id, 'promotion' => $promotion->fresh()];
    }

    public function stopOffer(Promotion $promotion, ?string $reason = null): array
    {
        if ($promotion->owner_type !== 'ai') {
            return ['success' => false, 'error' => 'The AI can only stop promotions it created itself.'];
        }

        $promotion->update([
            'status' => Promotion::STATUS_PAUSED,
            'description' => trim(($promotion->description ?? '')."\nStopped by AI: ".($reason ?: 'no longer needed.')),
        ]);

        return ['success' => true, 'promotion_id' => $promotion->id];
    }

    /**
     * Personal, single-use coupon for one specific customer -- used by
     * App\Services\CartRecoveryService when a cart has been idle past the
     * coupon threshold. Platform-funded (not restaurant-funded) since this
     * is a customer-retention tool the AI initiates on its own, not a
     * restaurant-requested campaign; still passes through the margin guard
     * using the cart's real subtotal as the estimated order value.
     */
    public function createPersonalCoupon(User $customer, ?int $restaurantId, float $cartSubtotal, string $reason): array
    {
        $discountPercent = (float) app(\App\Services\Ai\AiSettingsService::class)->get('ai_cart_coupon_discount_percent', 10);
        $expiryHours = (int) app(\App\Services\Ai\AiSettingsService::class)->get('ai_cart_coupon_expiry_hours', 48);

        $rewards = ['type' => 'percentage', 'value' => $discountPercent];
        $marginCheck = $this->marginGuard->checkPromotion($restaurantId, 'platform', $rewards, $cartSubtotal);
        if (! $marginCheck['allowed']) {
            return ['success' => false, 'error' => $marginCheck['reason'], 'margin_check' => $marginCheck];
        }

        $promotion = Promotion::create([
            'owner_type' => 'ai',
            'owner_id' => null,
            'restaurant_id' => $restaurantId,
            'title' => 'Personal cart reminder discount',
            'description' => $reason,
            'promotion_type' => 'festival_offer',
            'application_mode' => 'coupon',
            'status' => Promotion::STATUS_ACTIVE,
            'priority' => 50,
            'is_exclusive' => true,
            'funding_type' => 'platform',
            'starts_at' => now(),
            'ends_at' => now()->addHours($expiryHours),
            'targets' => [
                'restaurant_ids' => $restaurantId ? [$restaurantId] : [],
                'customer_ids' => [$customer->id],
            ],
            'conditions' => ['audience_type' => 'all'],
            'rewards' => $rewards,
            'stacking' => ['allow_multiple' => false, 'allow_coupon_promotion' => false, 'best_offer_only' => true],
            'fraud_rules' => ['one_per_user' => true],
            'visibility' => [],
        ]);

        $code = 'CART'.strtoupper(Str::random(6));
        $coupon = PromotionCouponCode::create([
            'promotion_id' => $promotion->id,
            'code' => $code,
            'user_id' => $customer->id,
            'usage_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => $promotion->ends_at,
        ]);

        return ['success' => true, 'promotion_id' => $promotion->id, 'coupon_code' => $coupon->code, 'discount_percent' => $discountPercent, 'expires_at' => $promotion->ends_at];
    }

    private function estimateOrderValue(?int $restaurantId, array $data): float
    {
        if (isset($data['estimated_order_value']) && (float) $data['estimated_order_value'] > 0) {
            return (float) $data['estimated_order_value'];
        }

        if ($restaurantId) {
            $avg = (float) Order::where('restaurant_id', $restaurantId)
                ->where('status', 'delivered')
                ->where('created_at', '>=', now()->subDays(30))
                ->avg('total');

            if ($avg > 0) {
                return $avg;
            }
        }

        return 300.0;
    }
}
