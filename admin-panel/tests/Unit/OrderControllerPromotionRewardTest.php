<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\OrderController;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class OrderControllerPromotionRewardTest extends TestCase
{
    public function test_reward_lines_can_be_laravel_collections(): void
    {
        $controller = (new ReflectionClass(OrderController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, 'applyRewardLinesToOrderItems');
        $method->setAccessible(true);

        $result = $method->invoke($controller, [
            [
                'id' => 10,
                'menu_item_id' => 10,
                'name' => 'Masala Dosa',
                'price' => 120,
                'quantity' => 1,
                'selected_variant' => [],
                'tags' => [],
            ],
        ], collect([
            [
                'menu_item_id' => 10,
                'quantity' => 1,
                'title' => 'Buy One Get One',
                'promotion_id' => 25,
            ],
        ]));

        $this->assertSame(2, $result[0]['quantity']);
        $this->assertSame('buy_get_free', $result[0]['promotion_line_type']);
        $this->assertSame(1, $result[0]['promotion_free_quantity']);
        $this->assertContains('promotion_free_units', $result[0]['tags']);
    }
}
