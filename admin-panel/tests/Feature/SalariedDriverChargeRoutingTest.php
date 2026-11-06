<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchWallet;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\GigIncentiveService;
use App\Services\PayoutCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charge routing: a salaried driver's delivery / long-distance / surge / night charges stay with admin; a per-order
 * (commission) driver keeps the delivery charge; a branch only ever earns its share of the restaurant commission.
 */
class SalariedDriverChargeRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_salaried_driver_charges_all_stay_with_admin_and_none_reach_the_driver_or_a_branch(): void
    {
        $branch = $this->branch();
        $driver = User::factory()->create(['earning_mode' => 'salary', 'monthly_salary' => 15000, 'branch_id' => $branch->id]);
        $order = $this->order($driver, null, ['delivery_fee' => 40, 'surge_fee' => 15, 'night_surcharge' => 10]);

        $this->assertTrue(app(PayoutCalculationService::class)->processOrderEarnings($order));
        $order->refresh();

        $this->assertSame(0.0, (float) $order->driver_earning, 'a salaried driver is not credited per order');
        $this->assertSame(0.0, (float) $order->branch_commission, 'a branch gets nothing from driver charges');
        $this->assertSame(0.0, (float) (BranchWallet::where('branch_id', $branch->id)->value('balance') ?? 0));
        // 40 delivery + 15 surge + 10 night, plus the (zero) restaurant commission share: all admin.
        $this->assertEqualsWithDelta(65.0, (float) $order->admin_commission - $this->restaurantCommissionToAdmin($order), 0.01);
    }

    public function test_commission_driver_keeps_the_delivery_charge(): void
    {
        $driver = User::factory()->create(['earning_mode' => 'commission']);
        $order = $this->order($driver, null, ['delivery_fee' => 40, 'surge_fee' => 15, 'night_surcharge' => 10]);

        app(PayoutCalculationService::class)->processOrderEarnings($order);
        $order->refresh();

        $this->assertGreaterThan(0, (float) $order->driver_earning);
        $this->assertEqualsWithDelta(40.0, (float) $order->driver_earning + (float) $order->driver_deduction, 0.01);
        // surge / night are NOT booked to admin for a commission driver (they reach the driver via the gig bonus).
        $this->assertEqualsWithDelta((float) $order->driver_deduction, (float) $order->admin_commission - $this->restaurantCommissionToAdmin($order), 0.01);
    }

    public function test_branch_earns_only_its_share_of_the_restaurant_commission(): void
    {
        $branch = $this->branch();
        $driver = User::factory()->create(['earning_mode' => 'salary', 'monthly_salary' => 15000, 'branch_id' => $branch->id]);
        $order = $this->order($driver, $branch, ['subtotal' => 1000, 'delivery_fee' => 40]);

        app(PayoutCalculationService::class)->processOrderEarnings($order);
        $order->refresh();

        $commission = (float) $order->platform_commission;
        $this->assertGreaterThan(0, $commission);
        $this->assertEqualsWithDelta($commission * 0.7, (float) $order->branch_commission, 0.01);
    }

    public function test_gig_bonus_skips_surge_and_night_for_a_salaried_driver_only(): void
    {
        $salaried = User::factory()->create(['earning_mode' => 'salary']);
        $perOrder = User::factory()->create(['earning_mode' => 'commission']);

        foreach ([$salaried, $perOrder] as $driver) {
            $order = $this->order($driver, null, ['surge_fee' => 15, 'night_surcharge' => 10]);
            $order->update(['delivered_at' => now()]);
            $gig = \App\Models\DriverGig::create([
                'driver_id' => $driver->id,
                'date' => now()->toDateString(),
                'start_time' => now()->subHour(),
                'end_time' => now()->addHour(),
                'base_pay' => 0,
                'order_incentive' => 0,
                'login_incentive' => 0,
                'min_login_minutes' => 0,
                'min_orders_required' => 0,
                'max_cancellations_allowed' => 99,
            ]);
            $result = app(GigIncentiveService::class)->calculateGigEarnings($gig, $driver->id);
            // base/incentive pay is 0 in this fixture, so total_earned is exactly the surge + night bonus.
            $bonus = (float) $result->total_earned;

            $this->assertEqualsWithDelta($driver->is($salaried) ? 0.0 : 25.0, $bonus, 0.01);
        }
    }

    private function restaurantCommissionToAdmin(Order $order): float
    {
        return round((float) $order->platform_commission - (float) $order->branch_commission, 2);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB' . random_int(1000, 9999),
            'owner_name' => 'Owner',
            'owner_email' => uniqid('b') . '@example.com',
            'owner_phone' => '98' . random_int(10000000, 99999999),
            'status' => 'active',
            'platform_commission_percent' => 20,
            'branch_share_percent' => 70,
            'admin_share_percent' => 30,
        ]);
    }

    private function order(User $driver, ?Branch $branch, array $overrides = []): Order
    {
        $customer = User::factory()->create();
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Routing Kitchen',
            'slug' => 'routing-kitchen-' . uniqid(),
            'address' => 'Main Street',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'phone' => '9999999999',
            'email' => uniqid('routing') . '@example.com',
            'cuisine' => ['Indian'],
            'is_open' => true,
            'is_pure_veg' => false,
            'min_order_amount' => 0,
            'delivery_fee' => 20,
            'delivery_time' => 30,
            'rating' => 4.7,
            'total_ratings' => 10,
            'is_featured' => true,
            'is_verified' => true,
        ]);

        $subtotal = $overrides['subtotal'] ?? 200;

        return Order::withoutEvents(fn () => Order::create(array_merge([
            'order_number' => 'TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'driver_id' => $driver->id,
            'branch_id' => $branch?->id,
            'order_type' => 'delivery',
            'items' => [],
            'subtotal' => $subtotal,
            'delivery_fee' => 0,
            'platform_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $subtotal,
            'payment_method' => 'cod',
            'payment_status' => 'success',
            'status' => 'delivered',
            'customer_name' => $customer->name,
            'customer_phone' => '9999999999',
            'customer_address' => ['address' => 'Main Street', 'phone' => '9999999999'],
            'delivery_address' => 'Main Street',
            'delivery_lat' => 22.5726,
            'delivery_lng' => 88.3639,
        ], array_diff_key($overrides, ['subtotal' => 1]))));
    }
}
