<?php

namespace App\Support;

class PromotionTypeRegistry
{
    public const ADMIN_TYPES = [
        'free_delivery' => ['label' => 'Free Delivery', 'reward_type' => 'free_delivery', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => ['no_value_required' => true]],
        'delivery_discount' => ['label' => 'Delivery Discount', 'reward_type' => 'delivery_discount', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'packaging_discount' => ['label' => 'Packaging Discount', 'reward_type' => 'packaging_discount', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'wallet_cashback' => ['label' => 'Wallet Cashback', 'reward_type' => 'wallet_credit', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => ['cashback' => true]],
        'reward_points' => ['label' => 'Reward Points', 'reward_type' => 'reward_points', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => ['points' => true]],
        'scratch_card' => ['label' => 'Scratch Card', 'reward_type' => 'scratch_card', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => ['no_value_required' => true]],
        'gift_voucher' => ['label' => 'Gift Voucher', 'reward_type' => 'gift_voucher', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'referral_bonus' => ['label' => 'Referral Bonus', 'reward_type' => 'referral_bonus', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'festival_offer' => ['label' => 'Festival Offer', 'reward_type' => 'percentage', 'discount_type' => 'percentage', 'target_type' => 'restaurant', 'defaults' => []],
        'flash_sale' => ['label' => 'Flash Sale', 'reward_type' => 'percentage', 'discount_type' => 'percentage', 'target_type' => 'restaurant', 'defaults' => []],
        'custom_rule' => ['label' => 'Custom Rule', 'reward_type' => 'custom_rule', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => ['no_value_required' => true]],
    ];

    public const RESTAURANT_TYPES = [
        'percentage_discount' => ['label' => 'Percentage Discount', 'reward_type' => 'percentage', 'discount_type' => 'percentage', 'target_type' => 'restaurant', 'defaults' => []],
        'flat_discount' => ['label' => 'Flat Discount', 'reward_type' => 'flat', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'fixed_price' => ['label' => 'Fixed Price', 'reward_type' => 'fixed_price', 'discount_type' => 'fixed', 'target_type' => 'restaurant', 'defaults' => []],
        'item_discount' => ['label' => 'Item Discount', 'reward_type' => 'item_discount', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => []],
        'category_discount' => ['label' => 'Category Discount', 'reward_type' => 'category_discount', 'discount_type' => 'percentage', 'target_type' => 'categories', 'defaults' => []],
        'combo_deal' => ['label' => 'Combo Deal', 'reward_type' => 'combo_deal', 'discount_type' => 'fixed', 'target_type' => 'items', 'defaults' => []],
        'meal_deal' => ['label' => 'Meal Deal', 'reward_type' => 'meal_deal', 'discount_type' => 'fixed', 'target_type' => 'items', 'defaults' => []],
        'bogo' => ['label' => 'BOGO', 'reward_type' => 'bogo', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => ['buy_quantity' => 1, 'free_quantity' => 1, 'no_value_required' => true]],
        'buy_x_get_y' => ['label' => 'Buy X Get Y', 'reward_type' => 'buy_x_get_y', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => ['buy_quantity' => 1, 'free_quantity' => 1, 'no_value_required' => true]],
        'buy_2_get_1' => ['label' => 'Buy 2 Get 1', 'reward_type' => 'buy_2_get_1', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => ['buy_quantity' => 2, 'free_quantity' => 1, 'no_value_required' => true]],
        'buy_3_get_1' => ['label' => 'Buy 3 Get 1', 'reward_type' => 'buy_3_get_1', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => ['buy_quantity' => 3, 'free_quantity' => 1, 'no_value_required' => true]],
        'buy_3_get_2' => ['label' => 'Buy 3 Get 2', 'reward_type' => 'buy_3_get_2', 'discount_type' => 'percentage', 'target_type' => 'items', 'defaults' => ['buy_quantity' => 3, 'free_quantity' => 2, 'no_value_required' => true]],
        'free_item' => ['label' => 'Free Item', 'reward_type' => 'free_item', 'discount_type' => 'fixed', 'target_type' => 'items', 'defaults' => ['no_value_required' => true]],
    ];

    private const ALIASES = [
        'restaurant_discount' => 'percentage_discount',
        'happy_hours' => 'percentage_discount',
        'festival_discount' => 'festival_offer',
        'deal_of_day' => 'flat_discount',
        'cashback' => 'wallet_cashback',
        'wallet_credit' => 'wallet_cashback',
        'buy_1_get_1' => 'bogo',
        'free_product' => 'free_item',
        'free_drink' => 'free_item',
        'free_dessert' => 'free_item',
        'subscription_discount' => 'festival_offer',
        'clearance_sale' => 'flash_sale',
        'ai_promotion' => 'custom_rule',
    ];

    public static function adminTypes(): array
    {
        return self::ADMIN_TYPES;
    }

    public static function restaurantTypes(): array
    {
        return self::RESTAURANT_TYPES;
    }

    public static function allTypes(): array
    {
        return self::ADMIN_TYPES + self::RESTAURANT_TYPES;
    }

    public static function typesForOwner(?string $ownerType): array
    {
        return in_array($ownerType, ['restaurant', 'branch'], true)
            ? self::RESTAURANT_TYPES
            : self::ADMIN_TYPES;
    }

    public static function normalize(?string $type): string
    {
        $key = strtolower(trim((string) $type));

        return self::ALIASES[$key] ?? $key;
    }
}
