<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'max_active_orders')) {
                $table->unsignedSmallInteger('max_active_orders')->nullable();
            }
        });

        AppSetting::updateOrCreate(
            ['key' => 'max_driver_assignment_attempts'],
            ['value' => '30', 'type' => 'number', 'description' => 'Maximum attempts to assign an order before auto-cancelling it.']
        );

        AppSetting::updateOrCreate(
            ['key' => 'max_active_orders_per_driver'],
            ['value' => '1', 'type' => 'number', 'description' => 'Default maximum active orders assigned to one driver. Route-matched orders can still be batched.']
        );

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

        Cache::forget('app_settings');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'max_active_orders')) {
                $table->dropColumn('max_active_orders');
            }
        });

        AppSetting::whereIn('key', [
            'max_driver_assignment_attempts',
            'max_active_orders_per_driver',
            'driver_route_match_radius_km',
            'multiple_order_bonus_two_orders',
            'multiple_order_bonus_three_plus_orders',
            'multiple_order_bonus_extra_order',
        ])->delete();

        Cache::forget('app_settings');
    }
};
