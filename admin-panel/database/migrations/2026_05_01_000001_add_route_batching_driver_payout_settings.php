<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::updateOrCreate(
            ['key' => 'driver_route_match_radius_km'],
            ['value' => '3', 'type' => 'number', 'description' => 'Maximum pickup/drop distance used to batch multiple orders on the same driver route.']
        );

        AppSetting::updateOrCreate(
            ['key' => 'multiple_order_bonus_two_orders'],
            ['value' => '10', 'type' => 'number', 'description' => 'Driver bonus for a matched two-order route.']
        );

        AppSetting::updateOrCreate(
            ['key' => 'multiple_order_bonus_three_plus_orders'],
            ['value' => '20', 'type' => 'number', 'description' => 'Driver bonus for a matched route with three or more orders.']
        );

        AppSetting::updateOrCreate(
            ['key' => 'multiple_order_bonus_extra_order'],
            ['value' => '5', 'type' => 'number', 'description' => 'Extra driver bonus per matched order after the third order.']
        );

        AppSetting::where('key', 'max_active_orders_per_driver')
            ->where('value', '1')
            ->update([
                'description' => 'Default maximum active orders assigned to one driver. Route-matched orders can still be batched.',
            ]);

        Cache::forget('app_settings');
    }

    public function down(): void
    {
        AppSetting::whereIn('key', [
            'driver_route_match_radius_km',
            'multiple_order_bonus_two_orders',
            'multiple_order_bonus_three_plus_orders',
            'multiple_order_bonus_extra_order',
        ])->delete();

        Cache::forget('app_settings');
    }
};
