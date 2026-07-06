<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CommissionSetting;
use App\Models\Order;
use App\Services\PayoutCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_earning_has_one_restaurant_commission(): void
    {
        $this->setCommission(CommissionSetting::RESTAURANT, 15);
        AppSetting::setValue('gst_rate', 18);
        AppSetting::setValue('gateway_fee_rate', 0);

        $order = new Order([
            'subtotal' => 1000,
            'total' => 1000,
            'payment_method' => 'cash',
        ]);

        $earning = app(PayoutCalculationService::class)->calculateRestaurantEarning($order);

        $this->assertSame(150.0, $earning['platform_commission']);
        $this->assertSame(27.0, $earning['gst_on_commission']);
        $this->assertSame(823.0, $earning['restaurant_earning']);
    }

    public function test_driver_earning_has_one_driver_commission(): void
    {
        $this->setCommission(CommissionSetting::DRIVER, 10);

        $order = new Order([
            'order_type' => 'delivery',
            'delivery_fee' => 100,
        ]);

        $earning = app(PayoutCalculationService::class)->calculateDriverEarning($order);

        $this->assertSame(10.0, $earning['driver_commission']);
        $this->assertSame(90.0, $earning['driver_earning']);
        $this->assertArrayNotHasKey('admin_delivery_commission', $earning);
        $this->assertArrayNotHasKey('driver_deduction', $earning);
    }

    private function setCommission(string $type, float $rate): void
    {
        CommissionSetting::updateOrCreate(
            ['type' => $type],
            [
                'name' => $type === CommissionSetting::DRIVER
                    ? 'Driver Earning Commission'
                    : 'Restaurant Earning Commission',
                'rate' => $rate,
                'calculation_type' => CommissionSetting::TYPE_PERCENTAGE,
                'is_active' => true,
            ]
        );
    }
}
