<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderPaymentReleaseTest extends TestCase
{
    public function test_pending_online_order_is_not_visible_to_restaurant_even_with_stale_cod_delivery_mode(): void
    {
        $order = new Order([
            'payment_method' => 'razorpay',
            'delivery_payment_mode' => 'cod',
            'payment_status' => 'pending',
        ]);

        $this->assertFalse($order->isCashOnDelivery());
        $this->assertFalse($order->isVisibleToRestaurant());
    }

    public function test_pending_cod_order_is_visible_to_restaurant(): void
    {
        $order = new Order([
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $this->assertTrue($order->isCashOnDelivery());
        $this->assertTrue($order->isVisibleToRestaurant());
    }

    public function test_successful_online_order_is_visible_to_restaurant(): void
    {
        $order = new Order([
            'payment_method' => 'stripe',
            'delivery_payment_mode' => 'online',
            'payment_status' => 'success',
        ]);

        $this->assertFalse($order->isCashOnDelivery());
        $this->assertTrue($order->isVisibleToRestaurant());
    }
}
