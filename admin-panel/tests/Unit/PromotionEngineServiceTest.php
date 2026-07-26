<?php

namespace Tests\Unit;

use App\Models\Promotion;
use App\Models\PromotionCouponCode;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_coupon_discount_with_engine_response_shape(): void
    {
        $restaurant = $this->restaurant();

        $promotion = Promotion::create([
            'owner_type' => 'admin',
            'title' => 'Welcome Offer',
            'promotion_type' => 'percentage_discount',
            'application_mode' => 'coupon',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'conditions' => ['min_order_amount' => 100, 'audience_type' => 'all'],
            'rewards' => ['type' => 'percentage', 'value' => 20, 'max_discount' => 75],
            'targets' => ['restaurant_ids' => [$restaurant->id]],
        ]);

        PromotionCouponCode::create([
            'promotion_id' => $promotion->id,
            'code' => 'SAVE20',
            'is_active' => true,
        ]);

        $result = app(PromotionEngineService::class)->validateCoupon([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 500,
            'delivery_fee' => 40,
            'platform_fee' => 10,
            'tax' => 25,
            'code' => 'SAVE20',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(75.0, $result['coupon_discount']);
        $this->assertSame(75.0, $result['total_savings']);
        $this->assertSame(500.0, $result['final_total']);
        $this->assertSame('SAVE20', $result['coupon_code']);
        $this->assertCount(1, $result['discount_lines']);
    }

    public function test_it_returns_invalid_reason_for_minimum_order(): void
    {
        $restaurant = $this->restaurant();
        $promotion = Promotion::create([
            'owner_type' => 'admin',
            'title' => 'High Cart Offer',
            'promotion_type' => 'flat_discount',
            'application_mode' => 'coupon',
            'status' => 'active',
            'conditions' => ['min_order_amount' => 1000, 'audience_type' => 'all'],
            'rewards' => ['type' => 'flat', 'value' => 100],
            'targets' => ['restaurant_ids' => [$restaurant->id]],
        ]);

        PromotionCouponCode::create([
            'promotion_id' => $promotion->id,
            'code' => 'BIG100',
            'is_active' => true,
        ]);

        $result = app(PromotionEngineService::class)->validateCoupon([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 250,
            'code' => 'BIG100',
            'user_id' => User::factory()->create()->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Minimum order amount', $result['message']);
    }

    public function test_bogo_requires_enough_item_quantity_before_discounting(): void
    {
        $restaurant = $this->restaurant();

        Promotion::create([
            'owner_type' => 'admin',
            'title' => 'Buy One Get One',
            'promotion_type' => 'bogo',
            'application_mode' => 'automatic',
            'status' => 'active',
            'conditions' => ['audience_type' => 'all'],
            'rewards' => ['type' => 'bogo', 'buy_quantity' => 1, 'free_quantity' => 1],
            'targets' => ['restaurant_ids' => [$restaurant->id]],
        ]);

        $singleItemResult = app(PromotionEngineService::class)->calculate([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 120,
            'items' => [
                ['id' => 10, 'price' => 120, 'quantity' => 1],
            ],
        ]);

        $qualifiedResult = app(PromotionEngineService::class)->calculate([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 240,
            'items' => [
                ['id' => 10, 'price' => 120, 'quantity' => 2],
            ],
        ]);

        $this->assertSame(0.0, $singleItemResult['item_discount']);
        $this->assertSame(120.0, $singleItemResult['final_total']);
        $this->assertFalse((bool) collect($singleItemResult['reward_lines'])->first()['included_in_cart']);
        $this->assertSame(1, collect($singleItemResult['reward_lines'])->first()['quantity']);
        $this->assertSame(120.0, $qualifiedResult['item_discount']);
        $this->assertSame(120.0, $qualifiedResult['final_total']);
        $this->assertTrue((bool) collect($qualifiedResult['reward_lines'])->first()['included_in_cart']);
        $this->assertSame(1, collect($qualifiedResult['reward_lines'])->first()['quantity']);
    }

    public function test_category_discount_requires_matching_cart_category(): void
    {
        $restaurant = $this->restaurant();

        Promotion::create([
            'owner_type' => 'admin',
            'title' => 'Dessert Deal',
            'promotion_type' => 'category_discount',
            'application_mode' => 'automatic',
            'status' => 'active',
            'conditions' => [
                'audience_type' => 'all',
                'contains_category_ids' => [5],
            ],
            'rewards' => [
                'type' => 'category_discount',
                'value' => 50,
                'category_ids' => [5],
            ],
            'targets' => ['restaurant_ids' => [$restaurant->id]],
        ]);

        $invalid = app(PromotionEngineService::class)->calculate([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
            'items' => [
                ['id' => 10, 'category_id' => 8, 'price' => 200, 'quantity' => 1],
            ],
        ]);

        $valid = app(PromotionEngineService::class)->calculate([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 200,
            'items' => [
                ['id' => 11, 'category_id' => 5, 'price' => 200, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(0.0, $invalid['item_discount']);
        $this->assertSame(100.0, $valid['item_discount']);
        $this->assertSame(100.0, $valid['final_total']);
    }

    public function test_enterprise_rewards_can_return_points_without_discounting_total(): void
    {
        $restaurant = $this->restaurant();

        Promotion::create([
            'owner_type' => 'admin',
            'title' => 'Points Boost',
            'promotion_type' => 'reward_points',
            'application_mode' => 'automatic',
            'status' => 'active',
            'conditions' => ['audience_type' => 'all'],
            'rewards' => ['type' => 'reward_points', 'value' => 250],
            'targets' => ['restaurant_ids' => [$restaurant->id]],
        ]);

        $result = app(PromotionEngineService::class)->calculate([
            'restaurant_id' => $restaurant->id,
            'subtotal' => 300,
        ]);

        $this->assertSame(250, $result['reward_points_earned']);
        $this->assertSame(0.0, $result['total_savings']);
        $this->assertSame(300.0, $result['final_total']);
        $this->assertCount(1, $result['applied_promotions']);
    }

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Promo Kitchen',
            'slug' => 'promo-kitchen-' . uniqid(),
            'address' => 'Main Street',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'phone' => '9999999999',
            'email' => uniqid('promo') . '@example.com',
            'cuisine' => ['Indian'],
            'is_open' => true,
            'is_pure_veg' => false,
            'min_order_amount' => 199,
            'delivery_fee' => 20,
            'delivery_time' => 30,
            'rating' => 4.7,
            'total_ratings' => 10,
            'is_featured' => true,
            'is_verified' => true,
        ]);
    }
}
