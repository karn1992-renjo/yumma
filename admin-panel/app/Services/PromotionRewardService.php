<?php

namespace App\Services;

use App\Models\Promotion;

class PromotionRewardService
{
    public function __construct(private readonly BuyXGetYPromotionWorkflowService $buyXGetY) {}

    public function preview(Promotion $promotion, array $context): ?array
    {
        if (! $this->buyXGetY->supports($promotion)) {
            return null;
        }

        return $this->buyXGetY->evaluate($promotion, $context);
    }

    public function calculate(Promotion $promotion, array $context): array
    {
        if ($this->buyXGetY->supports($promotion)) {
            $workflow = $this->buyXGetY->evaluate($promotion, $context);
            if (! ($workflow['eligible'] ?? false)) {
                return $this->emptyLine($promotion, $workflow);
            }

            $reward = $promotion->rewards ?? [];
            $type = strtolower((string) ($reward['type'] ?? $promotion->promotion_type));
            $bucket = $promotion->isCouponBased() ? 'coupon_discount' : 'item_discount';

            $maxDiscount = $reward['max_discount'] ?? null;
            $discountAmount = (float) ($workflow['discount_amount'] ?? 0);
            if ($maxDiscount !== null && $discountAmount > (float) $maxDiscount) {
                $discountAmount = (float) $maxDiscount;
            }

            return [
                'promotion_id' => $promotion->id,
                'title' => $promotion->title,
                'bucket' => $bucket,
                'type' => $type,
                'value' => (float) ($reward['value'] ?? 0),
                'discount_amount' => round($discountAmount, 2),
                'cashback_amount' => round((float) ($workflow['cashback_amount'] ?? 0), 2),
                'reward_points' => (int) ($workflow['reward_points'] ?? 0),
                'free_item_id' => data_get($workflow, 'reward_lines.0.item_id'),
                'gift_voucher_amount' => 0.0,
                'reward_payload' => ['workflow' => $workflow],
                'source_type' => $promotion->migrated_from_type,
                'source_id' => $promotion->migrated_from_id,
            ];
        }

        $reward = $promotion->rewards ?? [];
        $type = strtolower((string) ($reward['type'] ?? $promotion->promotion_type));
        $items = collect($context['items'] ?? []);
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $deliveryFee = (float) ($context['delivery_fee'] ?? 0);
        $packagingFee = (float) ($context['packaging_fee'] ?? 0);
        $value = (float) ($reward['value'] ?? 0);
        $maxDiscount = $reward['max_discount'] ?? null;
        $discount = 0.0;
        $bucket = $promotion->isCouponBased() ? 'coupon_discount' : 'promotion_discount';
        $cashback = 0.0;
        $rewardPoints = 0;
        $giftVoucherAmount = 0.0;
        $rewardPayload = [];

        if (in_array($type, ['percentage', 'percentage_discount', 'subscription_discount', 'festival_offer', 'flash_sale', 'clearance_sale', 'ai_promotion'], true)) {
            $discount = $subtotal * ($value / 100);
        } elseif (in_array($type, ['flat', 'fixed', 'fixed_amount', 'flat_discount'], true)) {
            $discount = $value;
        } elseif (in_array($type, ['fixed_price', 'fixed_selling_price'], true)) {
            $discount = max(0, $subtotal - $value);
        } elseif ($type === 'free_delivery') {
            $discount = $deliveryFee;
            $bucket = 'delivery_discount';
        } elseif ($type === 'delivery_discount') {
            $discount = $this->chargeDiscount($deliveryFee, $value, $reward);
            $bucket = 'delivery_discount';
        } elseif (in_array($type, ['packaging_discount', 'free_packaging'], true)) {
            $discount = $type === 'free_packaging'
                ? $packagingFee
                : $this->chargeDiscount($packagingFee, $value, $reward);
            $bucket = 'packaging_discount';
        } elseif (in_array($type, ['restaurant_discount', 'branch_discount'], true)) {
            $discount = $value > 0 && $value <= 100
                ? $subtotal * ($value / 100)
                : $value;
            $bucket = 'restaurant_discount';
        } elseif (in_array($type, ['item_discount', 'category_discount', 'brand_discount', 'combo_deal', 'meal_deal'], true)) {
            if (in_array($type, ['combo_deal', 'meal_deal'], true) && ! empty($reward['combo_groups'])) {
                $discount = $this->comboGroupDiscount($items, $reward, $value);
            } else {
                $base = $this->eligibleItemSubtotal($items, $reward);
                if (
                    $type === 'brand_discount'
                    && empty($reward['brand_ids'])
                    && empty($reward['item_ids'])
                    && empty($reward['category_ids'])
                ) {
                    $base = 0.0;
                }
                $discount = $value > 0 && $value <= 100
                    ? $base * ($value / 100)
                    : min($value, $base);
            }
            $bucket = 'item_discount';
        } elseif (in_array($type, ['buy_x_get_y', 'buy_x_get_x', 'bogo', 'buy_2_get_1', 'buy_3_get_1', 'buy_3_get_2', 'free_quantity'], true)) {
            $reward = $this->normalizeBogoReward($type, $reward);
            $discount = $this->freeQuantityDiscount($items, $reward);
            $bucket = 'item_discount';
        } elseif (in_array($type, ['free_item', 'free_product', 'free_drink', 'free_dessert'], true)) {
            $discount = $this->freeProductDiscount($items, $reward);
            $bucket = 'item_discount';
        } elseif (in_array($type, ['cashback', 'wallet_cashback', 'wallet_credit'], true)) {
            $cashback = $type === 'wallet_credit' ? $value : $subtotal * ($value / 100);
            $discount = 0.0;
            $bucket = 'cashback';
        } elseif ($type === 'reward_points') {
            $cashback = 0.0;
            $discount = 0.0;
            $bucket = 'reward_points';
            $rewardPoints = (int) ($reward['points'] ?? $value);
        } elseif ($type === 'scratch_card') {
            $cashback = 0.0;
            $discount = 0.0;
            $bucket = $type;
            $rewardPayload['scratch_card_pool'] = $reward['pool'] ?? [];
        } elseif ($type === 'gift_voucher') {
            $cashback = 0.0;
            $discount = 0.0;
            $bucket = $type;
            $giftVoucherAmount = round((float) $value, 2);
        } elseif ($type === 'referral_bonus') {
            $cashback = 0.0;
            $discount = 0.0;
            $bucket = $type;
            $bonusType = $reward['bonus_type'] ?? 'wallet_credit';
            if ($bonusType === 'reward_points') {
                $rewardPoints = (int) $value;
            } elseif ($bonusType === 'gift_voucher') {
                $giftVoucherAmount = round((float) $value, 2);
            } else {
                $cashback = $value;
            }
            $rewardPayload['referral_bonus_type'] = $bonusType;
        } elseif ($type === 'custom_rule') {
            $custom = $this->customRuleReward($promotion, $context, $reward);
            $discount = $custom['discount_amount'];
            $cashback = $custom['cashback_amount'];
            $rewardPoints = $custom['reward_points'];
            $giftVoucherAmount = $custom['gift_voucher_amount'];
            $bucket = $custom['bucket'];
            $rewardPayload = $custom['reward_payload'];
        }

        if ($type !== 'free_delivery' && $maxDiscount !== null && $discount > (float) $maxDiscount) {
            $discount = (float) $maxDiscount;
        }

        $cartCap = match ($bucket) {
            'delivery_discount' => $deliveryFee,
            'packaging_discount' => $packagingFee,
            default => $subtotal,
        };
        $discount = max(0, min($discount, $cartCap));
        $cashback = max(0, $cashback);

        return [
            'promotion_id' => $promotion->id,
            'title' => $promotion->title,
            'bucket' => $bucket,
            'type' => $type,
            'value' => $value,
            'discount_amount' => round($discount, 2),
            'cashback_amount' => round($cashback, 2),
            'reward_points' => $rewardPoints,
            'free_item_id' => $reward['free_item_id'] ?? $reward['item_id'] ?? null,
            'gift_voucher_amount' => $giftVoucherAmount,
            'reward_payload' => array_filter($rewardPayload, fn ($item) => $item !== null && $item !== []),
            'source_type' => $promotion->migrated_from_type,
            'source_id' => $promotion->migrated_from_id,
        ];
    }

    private function eligibleItemSubtotal($items, array $reward): float
    {
        $itemIds = array_map('intval', (array) ($reward['item_ids'] ?? []));
        $categoryIds = array_map('intval', (array) ($reward['category_ids'] ?? []));
        $brandIds = array_map('intval', (array) ($reward['brand_ids'] ?? []));
        $variantIds = array_map('intval', (array) ($reward['variant_ids'] ?? []));
        $addonIds = array_map('intval', (array) ($reward['addon_ids'] ?? []));
        $menuTags = array_filter(array_map('strtolower', (array) ($reward['menu_tags'] ?? [])));

        return (float) $items
            ->filter(function (array $item) use ($itemIds, $categoryIds, $brandIds, $variantIds, $addonIds, $menuTags) {
                if ($itemIds && ! in_array((int) ($item['id'] ?? $item['menu_item_id'] ?? 0), $itemIds, true)) {
                    return false;
                }

                if ($categoryIds && ! in_array((int) ($item['category_id'] ?? 0), $categoryIds, true)) {
                    return false;
                }

                if ($brandIds && ! in_array((int) ($item['brand_id'] ?? 0), $brandIds, true)) {
                    return false;
                }

                if ($variantIds && ! in_array((int) ($item['variant_id'] ?? 0), $variantIds, true)) {
                    return false;
                }

                if ($addonIds) {
                    $itemAddonIds = array_map('intval', (array) ($item['addon_ids'] ?? []));
                    if (! array_intersect($addonIds, $itemAddonIds)) {
                        return false;
                    }
                }

                if ($menuTags) {
                    $itemTags = array_filter(array_map('strtolower', (array) ($item['tags'] ?? [])));
                    if (! array_intersect($menuTags, $itemTags)) {
                        return false;
                    }
                }

                return true;
            })
            ->sum(fn (array $item) => ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1)));
    }

    private function comboGroupDiscount($items, array $reward, float $fallbackComboPrice): float
    {
        $cart = $items
            ->mapWithKeys(fn (array $item) => [
                (int) ($item['id'] ?? $item['menu_item_id'] ?? 0) => [
                    'price' => (float) ($item['price'] ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ],
            ])
            ->filter(fn ($item, int $id) => $id > 0);

        if ($cart->isEmpty()) {
            return 0.0;
        }

        return (float) collect($reward['combo_groups'] ?? [])
            ->filter(fn ($group) => is_array($group))
            ->sum(function (array $group) use ($cart, $fallbackComboPrice) {
                $itemIds = collect($group['item_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($itemIds->count() < 2 || ! $itemIds->every(fn (int $id) => $cart->has($id))) {
                    return 0.0;
                }

                $sets = $itemIds
                    ->map(fn (int $id) => (int) data_get($cart->get($id), 'quantity', 0))
                    ->min();
                if ($sets <= 0) {
                    return 0.0;
                }

                $groupSubtotal = (float) ($group['actual_price'] ?? 0);
                if ($groupSubtotal <= 0) {
                    $groupSubtotal = $itemIds->sum(fn (int $id) => (float) data_get($cart->get($id), 'price', 0));
                }
                $comboPrice = isset($group['price']) && $group['price'] !== null
                    ? (float) $group['price']
                    : $fallbackComboPrice;

                if ($comboPrice <= 0) {
                    return 0.0;
                }

                return max(0, $groupSubtotal - $comboPrice) * $sets;
            });
    }

    private function normalizeBogoReward(string $type, array $reward): array
    {
        return match ($type) {
            'buy_2_get_1' => array_merge(['buy_quantity' => 2, 'free_quantity' => 1], $reward),
            'buy_3_get_1' => array_merge(['buy_quantity' => 3, 'free_quantity' => 1], $reward),
            'buy_3_get_2' => array_merge(['buy_quantity' => 3, 'free_quantity' => 2], $reward),
            default => $reward,
        };
    }

    private function freeQuantityDiscount($items, array $reward): float
    {
        $buyQuantity = max(1, (int) ($reward['buy_quantity'] ?? 1));
        $freeQuantity = max(1, (int) ($reward['free_quantity'] ?? $reward['get_quantity'] ?? 1));
        $requiredQuantity = $buyQuantity;
        $eligible = $items
            ->filter(fn (array $item) => (int) ($item['quantity'] ?? 1) >= $requiredQuantity)
            ->sortBy(fn (array $item) => (float) ($item['price'] ?? 0))
            ->first();

        if (! $eligible) {
            return 0.0;
        }

        $sets = intdiv((int) ($eligible['quantity'] ?? 1), $requiredQuantity);
        $freeUnits = $sets * $freeQuantity;

        return min($freeUnits, (int) ($eligible['quantity'] ?? 1)) * (float) ($eligible['price'] ?? 0);
    }

    private function freeProductDiscount($items, array $reward): float
    {
        $freeItemId = (int) ($reward['free_item_id'] ?? $reward['item_id'] ?? 0);
        $freeItem = $items->first(function (array $item) use ($freeItemId) {
            if ($freeItemId <= 0) {
                return true;
            }

            return (int) ($item['id'] ?? $item['menu_item_id'] ?? 0) === $freeItemId;
        });

        return $freeItem ? (float) ($freeItem['price'] ?? 0) : (float) ($reward['free_item_value'] ?? 0);
    }

    private function customRuleReward(Promotion $promotion, array $context, array $reward): array
    {
        $config = $reward['custom_rule'] ?? [];
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($config) || $config === []) {
            return $this->emptyCustomRule();
        }

        if (! $this->customRuleConditionsMatch($config, $context)) {
            return $this->emptyCustomRule();
        }

        $ruleReward = data_get($config, 'reward', []);
        if (is_string($ruleReward)) {
            $ruleReward = ['type' => $ruleReward];
        }
        if (! is_array($ruleReward)) {
            return $this->emptyCustomRule();
        }

        $type = strtolower((string) ($ruleReward['type'] ?? ''));
        $value = (float) ($ruleReward['value'] ?? $ruleReward['amount'] ?? $reward['value'] ?? 0);
        $subtotal = (float) ($context['subtotal'] ?? 0);
        $deliveryFee = (float) ($context['delivery_fee'] ?? 0);
        $packagingFee = (float) ($context['packaging_fee'] ?? 0);
        $maxDiscount = $ruleReward['max_discount'] ?? $reward['max_discount'] ?? null;
        $discount = 0.0;
        $cashback = 0.0;
        $points = 0;
        $giftVoucher = 0.0;
        $bucket = 'custom_rule';

        if (in_array($type, ['percentage', 'percentage_discount'], true)) {
            $discount = $subtotal * ($value / 100);
            $bucket = 'promotion_discount';
        } elseif (in_array($type, ['flat', 'flat_discount', 'fixed_amount'], true)) {
            $discount = $value;
            $bucket = 'promotion_discount';
        } elseif ($type === 'free_delivery') {
            $discount = $deliveryFee;
            $bucket = 'delivery_discount';
        } elseif ($type === 'packaging_discount') {
            $discount = $this->chargeDiscount($packagingFee, $value, $ruleReward);
            $bucket = 'packaging_discount';
        } elseif (in_array($type, ['cashback', 'wallet_cashback', 'wallet_credit'], true)) {
            $cashback = $type === 'cashback' ? $subtotal * ($value / 100) : $value;
            $bucket = 'cashback';
        } elseif ($type === 'reward_points') {
            $points = max(0, (int) ($ruleReward['points'] ?? $value));
            $bucket = 'reward_points';
        } elseif (in_array($type, ['gift_voucher', 'gift_card'], true)) {
            $giftVoucher = round($value, 2);
            $bucket = 'gift_voucher';
        } else {
            return $this->emptyCustomRule();
        }

        if ($type !== 'free_delivery' && $maxDiscount !== null && $discount > (float) $maxDiscount) {
            $discount = (float) $maxDiscount;
        }

        return [
            'bucket' => $bucket,
            'discount_amount' => max(0, round($discount, 2)),
            'cashback_amount' => max(0, round($cashback, 2)),
            'reward_points' => $points,
            'gift_voucher_amount' => max(0, round($giftVoucher, 2)),
            'reward_payload' => [
                'custom_rule' => [
                    'promotion_id' => $promotion->id,
                    'config' => $config,
                ],
            ],
        ];
    }

    private function customRuleConditionsMatch(array $config, array $context): bool
    {
        $conditions = (array) ($config['conditions'] ?? []);
        if ($conditions === [] && isset($config['condition'])) {
            $conditions = ['expression' => $config['condition']];
        }

        $subtotal = (float) ($context['subtotal'] ?? 0);
        $quantity = collect($context['items'] ?? [])->sum(fn ($item) => (int) ($item['quantity'] ?? 1));

        foreach ([
            'min_order_amount' => fn ($value) => $subtotal >= (float) $value,
            'max_order_amount' => fn ($value) => $subtotal <= (float) $value,
            'min_quantity' => fn ($value) => $quantity >= (int) $value,
            'max_quantity' => fn ($value) => $quantity <= (int) $value,
        ] as $key => $checker) {
            if (array_key_exists($key, $conditions) && ! $checker($conditions[$key])) {
                return false;
            }
        }

        $expression = strtolower(trim((string) ($conditions['expression'] ?? '')));
        if ($expression !== '') {
            if (preg_match('/^subtotal\s*(>=|>|<=|<|==)\s*(\d+(?:\.\d+)?)$/', $expression, $matches)) {
                $expected = (float) $matches[2];

                return match ($matches[1]) {
                    '>=' => $subtotal >= $expected,
                    '>' => $subtotal > $expected,
                    '<=' => $subtotal <= $expected,
                    '<' => $subtotal < $expected,
                    '==' => abs($subtotal - $expected) < 0.01,
                    default => false,
                };
            }

            return false;
        }

        return true;
    }

    private function emptyCustomRule(): array
    {
        return [
            'bucket' => 'custom_rule',
            'discount_amount' => 0.0,
            'cashback_amount' => 0.0,
            'reward_points' => 0,
            'gift_voucher_amount' => 0.0,
            'reward_payload' => [],
        ];
    }

    private function chargeDiscount(float $charge, float $value, array $reward): float
    {
        $valueType = strtolower((string) ($reward['value_type'] ?? $reward['discount_type'] ?? ''));

        if ($valueType === 'percentage') {
            return $charge * (min(100, max(0, $value)) / 100);
        }

        if (in_array($valueType, ['fixed', 'flat'], true)) {
            return min(max(0, $value), $charge);
        }

        // Existing promotions did not store a mode. Preserve their historical
        // interpretation until they are edited and saved with an explicit mode.
        return $value > 0 && $value <= 100
            ? $charge * ($value / 100)
            : min(max(0, $value), $charge);
    }

    private function emptyLine(Promotion $promotion, array $workflow): array
    {
        return [
            'promotion_id' => $promotion->id,
            'title' => $promotion->title,
            'bucket' => 'item_discount',
            'type' => strtolower((string) $promotion->promotion_type),
            'value' => 0,
            'discount_amount' => 0.0,
            'cashback_amount' => 0.0,
            'reward_points' => 0,
            'free_item_id' => null,
            'gift_voucher_amount' => 0.0,
            'reward_payload' => ['workflow' => $workflow],
            'source_type' => $promotion->migrated_from_type,
            'source_id' => $promotion->migrated_from_id,
        ];
    }
}
