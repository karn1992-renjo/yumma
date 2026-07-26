<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BuyXGetYPromotionWorkflowService
{
    private const TYPES = [
        'bogo',
        'buy_1_get_1',
        'buy_2_get_1',
        'buy_3_get_1',
        'buy_3_get_2',
        'buy_x_get_y',
        'buy_x_get_x',
        'buy_category_get_item',
        'buy_category_get_category',
        'buy_restaurant_item_get_free_item',
        'buy_amount_get_free_item',
        'buy_combo_get_reward',
        'buy_meal_get_dessert',
        'buy_pizza_get_drink',
        'buy_burger_get_fries',
        'buy_product_a_get_product_b',
        'buy_any_2_get_cheapest_free',
        'buy_n_items_get_cheapest_discount',
        'buy_quantity_get_wallet_cashback',
        'buy_quantity_get_reward_points',
        'free_item',
        'free_product',
        'free_drink',
        'free_dessert',
        'free_quantity',
    ];

    public function supports(Promotion $promotion): bool
    {
        $type = $this->normalize($promotion->promotion_type);
        $rewardType = $this->normalize(data_get($promotion->rewards ?? [], 'type'));

        return in_array($type, self::TYPES, true)
            || in_array($rewardType, self::TYPES, true)
            || data_get($promotion->conditions ?? [], 'buy_rule') !== null
            || data_get($promotion->rewards ?? [], 'reward_rule') !== null;
    }

    public function evaluate(Promotion $promotion, array $context): array
    {
        $type = $this->normalize($promotion->promotion_type);
        $rewardType = $this->normalize(data_get($promotion->rewards ?? [], 'type'));
        $buyRule = $this->buyRule($promotion);
        $rewardRule = $this->rewardRule($promotion);
        $items = $this->normalizeItems($context['items'] ?? []);
        $eligibleItems = $items->filter(fn (array $item) => $this->matchesBuyRule($item, $buyRule))->values();
        $buyQuantity = max(1, (int) ($buyRule['buy_quantity'] ?? 1));
        $rewardQuantity = max(1, (int) ($rewardRule['reward_quantity'] ?? $rewardRule['free_quantity'] ?? $buyRule['reward_quantity'] ?? 1));
        $minAmount = $this->nullableFloat($buyRule['min_amount'] ?? $buyRule['minimum_amount'] ?? null);
        $maxAmount = $this->nullableFloat($buyRule['max_amount'] ?? $buyRule['maximum_amount'] ?? null);
        $eligibleAmount = round((float) $eligibleItems->sum(fn (array $item) => $item['price'] * $item['quantity']), 2);
        $eligibleQuantity = (int) $eligibleItems->sum('quantity');
        $maxRewards = $rewardRule['max_reward_quantity'] ?? $rewardRule['maximum_reward'] ?? $rewardRule['max_reward'] ?? null;
        $maxRewards = $maxRewards === null ? null : max(0, (int) $maxRewards);

        if ($maxAmount !== null && $eligibleAmount > $maxAmount) {
            return $this->notEligible(
                $promotion,
                'max_amount_exceeded',
                'Cart value is above this offer limit.',
                $this->progress($promotion, $buyQuantity, $eligibleQuantity, $minAmount, $eligibleAmount, true)
            );
        }

        if ($minAmount !== null && $eligibleAmount < $minAmount) {
            return $this->notEligible(
                $promotion,
                'min_amount_not_met',
                'Add '.number_format($minAmount - $eligibleAmount, 2, '.', '').' more to unlock this offer.',
                $this->progress($promotion, $buyQuantity, $eligibleQuantity, $minAmount, $eligibleAmount)
            );
        }

        if ($eligibleQuantity < $buyQuantity) {
            return $this->notEligible(
                $promotion,
                'min_quantity_not_met',
                'Add '.($buyQuantity - $eligibleQuantity).' more eligible item(s) to unlock this offer.',
                $this->progress($promotion, $buyQuantity, $eligibleQuantity, $minAmount, $eligibleAmount)
            );
        }

        $rewardTypeForQuantity = $this->normalize($rewardRule['reward_type'] ?? 'same_item');
        $includedRewardUnits = $this->includedRewardUnits($eligibleItems, $rewardTypeForQuantity, $buyQuantity, $rewardQuantity);
        $rewardCandidates = $this->rewardCandidates($eligibleItems, $rewardRule);
        $rewardIncludedInCart = (bool) (
            $rewardRule['included_in_cart']
            ?? $rewardRule['reward_included_in_cart']
            ?? false
        );
        $rewardIncludedInCart = $rewardIncludedInCart
            && $includedRewardUnits > 0
            && $this->rewardCandidatesAreInCart($rewardCandidates, $eligibleItems);

        $setCount = $rewardIncludedInCart
            ? intdiv($includedRewardUnits, $rewardQuantity)
            : intdiv($eligibleQuantity, $buyQuantity);
        if ($maxRewards !== null) {
            $setCount = min($setCount, $maxRewards);
        }

        if ($rewardCandidates->isEmpty() && ! in_array($rewardType, ['wallet_cashback', 'reward_points'], true)) {
            return $this->notEligible(
                $promotion,
                'reward_unavailable',
                'Reward item is unavailable.',
                $this->progress($promotion, $buyQuantity, $eligibleQuantity, $minAmount, $eligibleAmount, true)
            );
        }

        $rewardUnits = max(1, $setCount) * $rewardQuantity;
        if ($maxRewards !== null) {
            $rewardUnits = min($rewardUnits, $maxRewards);
        }
        $selectionRequired = (bool) ($rewardRule['manual_selection'] ?? $rewardRule['selection_required'] ?? false);
        $autoAdd = (bool) ($rewardRule['auto_add'] ?? true);
        if ($rewardCandidates->count() > 1 && ! $autoAdd) {
            $selectionRequired = true;
        }

        $discountUnits = $rewardIncludedInCart ? min($rewardUnits, $includedRewardUnits) : 0;
        $discount = $this->discountAmount($type, $rewardType, $eligibleItems, $rewardCandidates, $buyQuantity, $rewardQuantity, $discountUnits, $rewardRule);
        $rewardLines = $selectionRequired
            ? collect()
            : $this->rewardLines($promotion, $rewardCandidates, $rewardUnits, $rewardRule, $discount, $rewardIncludedInCart, $buyQuantity, $rewardQuantity);
        $cashback = $this->cashbackAmount($rewardType, $rewardRule, $eligibleAmount);
        $points = $rewardType === 'reward_points'
            ? (int) ($rewardRule['points'] ?? $rewardRule['reward_value'] ?? $rewardRule['value'] ?? 0)
            : 0;

        return [
            'eligible' => true,
            'reason_code' => null,
            'reason' => null,
            'promotion_id' => $promotion->id,
            'promotion_type' => $promotion->promotion_type,
            'title' => $promotion->title,
            'buy_quantity' => $buyQuantity,
            'reward_quantity' => $rewardQuantity,
            'eligible_quantity' => $eligibleQuantity,
            'eligible_amount' => $eligibleAmount,
            'set_count' => $setCount,
            'reward_units' => $rewardUnits,
            'reward_included_in_cart' => $rewardIncludedInCart,
            'discount_amount' => round($discount, 2),
            'cashback_amount' => round($cashback, 2),
            'reward_points' => $points,
            'action' => $selectionRequired
                ? 'select_reward'
                : ($autoAdd && $rewardLines->isNotEmpty() ? 'auto_add_reward' : 'none'),
            'auto_add' => $autoAdd,
            'selection_required' => $selectionRequired,
            'progress' => $this->progress($promotion, $buyQuantity, $eligibleQuantity, $minAmount, $eligibleAmount, true),
            'reward_candidates' => $rewardCandidates->values()->all(),
            'reward_lines' => $rewardLines->values()->all(),
        ];
    }

    private function buyRule(Promotion $promotion): array
    {
        $type = $this->normalize($promotion->promotion_type);
        $conditions = $promotion->conditions ?? [];
        $targets = $promotion->targets ?? [];
        $reward = $promotion->rewards ?? [];
        $base = [
            'buy_quantity' => match ($type) {
                'bogo', 'buy_1_get_1' => 1,
                'buy_2_get_1', 'buy_any_2_get_cheapest_free' => 2,
                'buy_3_get_1', 'buy_3_get_2' => 3,
                default => data_get($conditions, 'min_quantity', 1),
            },
            'scope' => data_get($conditions, 'scope', 'specific_items'),
            'quantity_grouping' => data_get($conditions, 'quantity_grouping', in_array($type, ['bogo', 'buy_1_get_1'], true) ? 'per_item' : 'across_items'),
            'item_ids' => data_get($targets, 'item_ids', data_get($targets, 'menu_item_ids', data_get($conditions, 'contains_item_ids', data_get($reward, 'item_ids', [])))),
            'category_ids' => data_get($targets, 'category_ids', data_get($conditions, 'contains_category_ids', data_get($reward, 'category_ids', []))),
            'brand_ids' => data_get($targets, 'brand_ids', []),
            'variant_ids' => data_get($targets, 'variant_ids', []),
            'addon_ids' => data_get($targets, 'addon_ids', []),
            'menu_tags' => data_get($targets, 'menu_tags', []),
            'food_types' => data_get($targets, 'food_types', []),
            'min_amount' => data_get($conditions, 'min_order_amount'),
            'max_amount' => data_get($conditions, 'max_order_amount'),
        ];

        return array_replace($base, (array) data_get($conditions, 'buy_rule', []));
    }

    private function rewardRule(Promotion $promotion): array
    {
        $type = $this->normalize($promotion->promotion_type);
        $reward = $promotion->rewards ?? [];
        $configuredFreeItemId = data_get($reward, 'free_item_id') ?: data_get($reward, 'item_id');
        $rewardItemIds = in_array($type, ['free_item', 'free_product', 'free_drink', 'free_dessert'], true) && $configuredFreeItemId
            ? [(int) $configuredFreeItemId]
            : data_get($reward, 'item_ids', array_filter([
                data_get($reward, 'free_item_id'),
                data_get($reward, 'item_id'),
            ]));
        $base = [
            'reward_type' => $this->normalizedRewardType(data_get($reward, 'type', match ($type) {
                'buy_quantity_get_wallet_cashback' => 'wallet_cashback',
                'buy_quantity_get_reward_points' => 'reward_points',
                default => 'same_item',
            })),
            'reward_quantity' => match ($type) {
                'buy_3_get_1' => 1,
                'buy_3_get_2' => 2,
                default => data_get($reward, 'free_quantity', data_get($reward, 'get_quantity', 1)),
            },
            'reward_discount_type' => data_get($reward, 'discount_type', 'free'),
            'reward_discount_value' => data_get($reward, 'value', 100),
            'reward_priority' => in_array($type, ['buy_any_2_get_cheapest_free', 'buy_n_items_get_cheapest_discount'], true)
                ? 'cheapest'
                : data_get($reward, 'reward_priority', 'configured_order'),
            'category_ids' => data_get($reward, 'category_ids', []),
            'variant_ids' => data_get($reward, 'variant_ids', []),
            'addon_ids' => data_get($reward, 'addon_ids', []),
            'auto_add' => data_get($reward, 'auto_add', true),
            'manual_selection' => data_get($reward, 'manual_selection', false),
            'max_reward_quantity' => data_get($reward, 'max_reward_quantity', data_get($reward, 'max_reward')),
            'value' => data_get($reward, 'value'),
            'points' => data_get($reward, 'points'),
            'item_ids' => $rewardItemIds,
        ];

        return array_replace($base, (array) data_get($reward, 'reward_rule', []));
    }

    private function normalizeItems(array $items): Collection
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'id' => (int) ($item['id'] ?? $item['menu_item_id'] ?? 0),
                'menu_item_id' => (int) ($item['menu_item_id'] ?? $item['id'] ?? 0),
                'category_id' => (int) ($item['category_id'] ?? 0),
                'brand_id' => (int) ($item['brand_id'] ?? 0),
                'variant_id' => (int) ($item['variant_id'] ?? data_get($item, 'selected_variant.id', 0)),
                'addon_ids' => array_map('intval', (array) ($item['addon_ids'] ?? [])),
                'tags' => array_map(fn ($tag) => $this->normalize($tag), (array) ($item['tags'] ?? [])),
                'food_type' => $this->normalize($item['food_type'] ?? ($item['is_veg'] ?? null)),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => max(0, (float) ($item['price'] ?? $item['unit_price'] ?? 0)),
                'name' => (string) ($item['name'] ?? 'Menu item'),
                'line_type' => $this->normalize($item['line_type'] ?? ''),
            ])
            ->filter(fn (array $item) => $item['id'] > 0 && $item['line_type'] !== 'promotion_reward')
            ->values();
    }

    private function matchesBuyRule(array $item, array $rule): bool
    {
        $checks = [];
        $checks[] = $this->matchesIds($item['id'], $rule['item_ids'] ?? $rule['menu_item_ids'] ?? []);
        $checks[] = $this->matchesIds($item['category_id'], $rule['category_ids'] ?? []);
        $checks[] = $this->matchesIds($item['brand_id'], $rule['brand_ids'] ?? []);
        $checks[] = $this->matchesIds($item['variant_id'], $rule['variant_ids'] ?? []);

        $addonIds = array_map('intval', (array) ($rule['addon_ids'] ?? []));
        $checks[] = $addonIds === [] ? null : (bool) array_intersect($addonIds, $item['addon_ids']);

        $menuTags = array_map(fn ($tag) => $this->normalize($tag), (array) ($rule['menu_tags'] ?? []));
        $checks[] = $menuTags === [] ? null : (bool) array_intersect($menuTags, $item['tags']);

        $foodTypes = array_map(fn ($type) => $this->normalize($type), (array) ($rule['food_types'] ?? []));
        $checks[] = $foodTypes === [] ? null : in_array($item['food_type'], $foodTypes, true);

        $activeChecks = array_values(array_filter($checks, fn ($check) => $check !== null));
        if ($activeChecks === []) {
            return true;
        }

        return ! in_array(false, $activeChecks, true);
    }

    private function rewardCandidates(Collection $eligibleItems, array $rewardRule): Collection
    {
        $rewardType = $this->normalize($rewardRule['reward_type'] ?? 'same_item');
        $itemIds = array_map('intval', (array) ($rewardRule['item_ids'] ?? $rewardRule['menu_item_ids'] ?? []));
        $categoryIds = array_map('intval', (array) ($rewardRule['category_ids'] ?? []));

        if ($itemIds) {
            $items = MenuItem::query()
                ->whereIn('id', $itemIds)
                ->get()
                ->map(fn (MenuItem $item) => $this->candidateFromMenuItem($item));

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        if ($categoryIds) {
            return MenuItem::query()
                ->whereIn('category_id', $categoryIds)
                ->where('is_available', true)
                ->limit(12)
                ->get()
                ->map(fn (MenuItem $item) => $this->candidateFromMenuItem($item));
        }

        if (in_array($rewardType, ['same_item', 'any_item', 'category', 'drink', 'dessert'], true)) {
            return $eligibleItems
                ->map(fn (array $item) => [
                    'item_id' => $item['id'],
                    'menu_item_id' => $item['id'],
                    'title' => $item['name'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'category_id' => $item['category_id'] ?: null,
                    'available' => true,
                ])
                ->unique('item_id')
                ->values();
        }

        if (in_array($rewardType, ['wallet_cashback', 'reward_points'], true)) {
            return collect([[
                'item_id' => null,
                'title' => Str::headline($rewardType),
                'price' => 0,
                'available' => true,
            ]]);
        }

        return collect();
    }

    private function rewardCandidatesAreInCart(Collection $rewardCandidates, Collection $eligibleItems): bool
    {
        if ($rewardCandidates->isEmpty()) {
            return false;
        }

        $eligibleIds = $eligibleItems
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        return $rewardCandidates->every(function (array $candidate) use ($eligibleIds) {
            $candidateId = (int) ($candidate['menu_item_id'] ?? $candidate['item_id'] ?? 0);
            return $candidateId > 0 && in_array($candidateId, $eligibleIds, true);
        });
    }

    private function includedRewardUnits(Collection $eligibleItems, string $rewardType, int $buyQuantity, int $rewardQuantity): int
    {
        if ($rewardQuantity <= 0 || ! in_array($rewardType, ['same_item', 'any_item'], true)) {
            return 0;
        }

        $groupSize = max(1, $buyQuantity + $rewardQuantity);

        return (int) $eligibleItems->sum(
            fn (array $item) => intdiv((int) $item['quantity'], $groupSize) * $rewardQuantity
        );
    }

    private function candidateFromMenuItem(MenuItem $item): array
    {
        return [
            'item_id' => $item->id,
            'menu_item_id' => $item->id,
            'title' => $item->name,
            'name' => $item->name,
            'price' => (float) $item->getFinalPriceAttribute(),
            'category_id' => $item->category_id,
            'image' => $item->image_url,
            'image_url' => $item->image_url,
            'available' => (bool) $item->is_available,
        ];
    }

    private function discountAmount(
        string $type,
        string $rewardType,
        Collection $eligibleItems,
        Collection $rewardCandidates,
        int $buyQuantity,
        int $rewardQuantity,
        int $rewardUnits,
        array $rewardRule
    ): float {
        if (in_array($rewardType, ['wallet_cashback', 'reward_points'], true)) {
            return 0.0;
        }

        $discountType = $this->normalize($rewardRule['reward_discount_type'] ?? 'free');
        $discountValue = (float) ($rewardRule['reward_discount_value'] ?? $rewardRule['value'] ?? 100);

        $basis = 0.0;
        if (($rewardRule['reward_priority'] ?? null) === 'cheapest' || str_contains($type, 'cheapest')) {
            $basis = $eligibleItems
                ->flatMap(fn (array $item) => array_fill(0, $item['quantity'], $item['price']))
                ->sort()
                ->take($rewardUnits)
                ->sum();
        } else {
            $remainingRewardUnits = $rewardUnits;
            $basis = $eligibleItems->sum(function (array $item) use ($buyQuantity, $rewardQuantity, &$remainingRewardUnits) {
                if ($remainingRewardUnits <= 0) {
                    return 0;
                }

                $freeUnits = intdiv($item['quantity'], $buyQuantity) * $rewardQuantity;
                $freeUnits = min($freeUnits, $remainingRewardUnits);
                $remainingRewardUnits -= $freeUnits;

                return $freeUnits * $item['price'];
            });
        }

        return match ($discountType) {
            'percentage' => $basis * ($discountValue / 100),
            'flat', 'fixed', 'fixed_amount' => min($discountValue, $basis),
            default => $basis,
        };
    }

    private function cashbackAmount(string $rewardType, array $rewardRule, float $eligibleAmount): float
    {
        if (! in_array($rewardType, ['wallet_cashback', 'cashback'], true)) {
            return 0.0;
        }

        $value = (float) ($rewardRule['reward_value'] ?? $rewardRule['value'] ?? 0);
        $discountType = $this->normalize($rewardRule['reward_discount_type'] ?? 'flat');

        return $discountType === 'percentage' ? $eligibleAmount * ($value / 100) : $value;
    }

    private function rewardLines(
        Promotion $promotion,
        Collection $candidates,
        int $rewardUnits,
        array $rewardRule,
        float $discount,
        bool $includedInCart,
        int $buyQuantity,
        int $rewardQuantity
    ): Collection
    {
        $remainingUnits = max(1, $rewardUnits);
        $lines = collect();

        foreach ($candidates as $candidate) {
            if ($remainingUnits <= 0) {
                break;
            }

            $candidateQuantity = array_key_exists('quantity', $candidate)
                ? max(1, (int) $candidate['quantity'])
                : $remainingUnits;
            $quantity = min($remainingUnits, $candidateQuantity);
            $remainingUnits -= $quantity;

            $lines->push([
                'promotion_id' => $promotion->id,
                'promotion_type' => $promotion->promotion_type,
                'title' => $promotion->title,
                'line_type' => 'promotion_reward',
                'reward_type' => $rewardRule['reward_type'] ?? 'same_item',
                'item_id' => $candidate['item_id'] ?? null,
                'menu_item_id' => $candidate['menu_item_id'] ?? $candidate['item_id'] ?? null,
                'name' => $candidate['name'] ?? $candidate['title'] ?? 'Reward',
                'quantity' => $quantity,
                'unit_price' => round((float) ($candidate['price'] ?? 0), 2),
                'image' => $candidate['image'] ?? $candidate['image_url'] ?? null,
                'image_url' => $candidate['image_url'] ?? $candidate['image'] ?? null,
                'category_id' => $candidate['category_id'] ?? null,
                'discount_amount' => $rewardUnits > 0
                    ? round($discount * ($quantity / max(1, $rewardUnits)), 2)
                    : 0,
                'payable_amount' => 0,
                'included_in_cart' => $includedInCart,
                'buy_quantity' => $buyQuantity,
                'paid_quantity' => $includedInCart
                    ? max(0, ((int) ($candidate['quantity'] ?? 0)) - $rewardUnits)
                    : $buyQuantity,
                'auto_add' => (bool) ($rewardRule['auto_add'] ?? true),
                'selection_required' => false,
            ]);
        }

        return $lines->values();
    }

    private function progress(
        Promotion $promotion,
        int $buyQuantity,
        int $eligibleQuantity,
        ?float $minAmount,
        float $eligibleAmount,
        bool $unlocked = false
    ): array {
        if ($minAmount !== null && $eligibleAmount < $minAmount) {
            $remaining = round($minAmount - $eligibleAmount, 2);

            return [[
                'promotion_id' => $promotion->id,
                'title' => $promotion->title,
                'type' => 'amount',
                'current' => $eligibleAmount,
                'required' => $minAmount,
                'remaining' => $remaining,
                'progress' => $minAmount > 0 ? min(1, $eligibleAmount / $minAmount) : 1,
                'unlocked' => false,
                'message' => 'Add '.number_format($remaining, 2, '.', '').' more to unlock '.$promotion->title,
            ]];
        }

        $remaining = max(0, $buyQuantity - min($eligibleQuantity, $buyQuantity));

        return [[
            'promotion_id' => $promotion->id,
            'title' => $promotion->title,
            'type' => 'quantity',
            'current' => $eligibleQuantity,
            'required' => $buyQuantity,
            'remaining' => $remaining,
            'progress' => $buyQuantity > 0 ? min(1, $eligibleQuantity / $buyQuantity) : 1,
            'unlocked' => $unlocked || $remaining === 0,
            'message' => $remaining > 0
                ? 'Add '.$remaining.' more eligible item(s) to unlock '.$promotion->title
                : $promotion->title.' unlocked',
        ]];
    }

    private function notEligible(Promotion $promotion, string $code, string $reason, array $progress): array
    {
        return [
            'eligible' => false,
            'promotion_id' => $promotion->id,
            'promotion_type' => $promotion->promotion_type,
            'title' => $promotion->title,
            'reason_code' => $code,
            'reason' => $reason,
            'progress' => $progress,
            'reward_candidates' => [],
            'reward_lines' => [],
            'action' => 'none',
            'discount_amount' => 0,
            'cashback_amount' => 0,
            'reward_points' => 0,
        ];
    }

    private function matchesIds(int $id, mixed $allowed): ?bool
    {
        $ids = array_map('intval', (array) $allowed);
        $ids = array_values(array_filter($ids));

        return $ids === [] ? null : in_array($id, $ids, true);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    private function normalizedRewardType(mixed $value): string
    {
        $type = $this->normalize($value);

        return in_array($type, self::TYPES, true) ? 'same_item' : $type;
    }

    private function normalize(mixed $value): string
    {
        return str_replace('-', '_', strtolower(trim((string) $value)));
    }
}
