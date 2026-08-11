<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RewardPointTransaction;
use App\Models\User;
use App\Services\OrderRewardPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRewardPointServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivered_order_credits_floor_of_final_total_once(): void
    {
        $user = User::factory()->create(['reward_points_balance' => 0]);
        $order = $this->order($user, 499.75);

        $service = app(OrderRewardPointService::class);
        $service->creditForDeliveredOrder($order);
        $service->creditForDeliveredOrder($order->fresh());

        $this->assertSame(499, (int) $user->fresh()->reward_points_balance);
        $this->assertSame(499, (int) $order->fresh()->reward_points_earned);
        $this->assertSame(1, RewardPointTransaction::where('reference_type', OrderRewardPointService::REFERENCE_EARNING)
            ->where('reference_id', $order->id)
            ->count());
    }

    public function test_refunded_delivered_order_reverses_points_once(): void
    {
        $user = User::factory()->create(['reward_points_balance' => 0]);
        $order = $this->order($user, 500);
        $service = app(OrderRewardPointService::class);

        $service->creditForDeliveredOrder($order);
        $service->reverseForRefundedOrder($order->fresh());
        $service->reverseForRefundedOrder($order->fresh());

        $this->assertSame(0, (int) $user->fresh()->reward_points_balance);
        $this->assertSame(0, (int) $order->fresh()->reward_points_earned);
        $this->assertSame(1, RewardPointTransaction::where('reference_type', OrderRewardPointService::REFERENCE_REVERSAL)
            ->where('reference_id', $order->id)
            ->count());
    }

    public function test_non_delivered_order_does_not_credit_points(): void
    {
        $user = User::factory()->create(['reward_points_balance' => 0]);
        $order = $this->order($user, 500, 'cancelled');

        app(OrderRewardPointService::class)->creditForDeliveredOrder($order);

        $this->assertSame(0, (int) $user->fresh()->reward_points_balance);
        $this->assertSame(0, RewardPointTransaction::where('order_id', $order->id)->count());
    }

    private function order(User $user, float $total, string $status = 'delivered'): Order
    {
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Rewards Kitchen',
            'slug' => 'rewards-kitchen-' . uniqid(),
            'address' => 'Main Street',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'phone' => '9999999999',
            'email' => uniqid('rewards') . '@example.com',
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

        return Order::withoutEvents(fn () => Order::create([
            'order_number' => 'TEST-' . uniqid(),
            'customer_id' => $user->id,
            'restaurant_id' => $restaurant->id,
            'order_type' => 'delivery',
            'items' => [],
            'subtotal' => $total,
            'delivery_fee' => 0,
            'platform_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'payment_method' => 'cod',
            'payment_status' => 'success',
            'status' => $status,
            'customer_name' => $user->name,
            'customer_phone' => '9999999999',
            'customer_address' => ['address' => 'Main Street', 'phone' => '9999999999'],
            'delivery_address' => 'Main Street',
            'delivery_lat' => 22.5726,
            'delivery_lng' => 88.3639,
        ]));
    }
}