<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurants')) {
            return;
        }

        $missingColumns = [
            'delivery_radius' => !Schema::hasColumn('restaurants', 'delivery_radius'),
            'restaurant_type' => !Schema::hasColumn('restaurants', 'restaurant_type'),
            'dining_charge' => !Schema::hasColumn('restaurants', 'dining_charge'),
            'dining_settings' => !Schema::hasColumn('restaurants', 'dining_settings'),
            'weekly_timings' => !Schema::hasColumn('restaurants', 'weekly_timings'),
            'timezone' => !Schema::hasColumn('restaurants', 'timezone'),
            'auto_accept_orders' => !Schema::hasColumn('restaurants', 'auto_accept_orders'),
            'order_lead_time' => !Schema::hasColumn('restaurants', 'order_lead_time'),
            'same_day_delivery' => !Schema::hasColumn('restaurants', 'same_day_delivery'),
        ];

        if (!in_array(true, $missingColumns, true)) {
            return;
        }

        Schema::table('restaurants', function (Blueprint $table) use ($missingColumns) {
            if ($missingColumns['delivery_radius']) {
                $table->decimal('delivery_radius', 8, 2)->default(10);
            }

            if ($missingColumns['restaurant_type']) {
                $table->string('restaurant_type')->default('delivery');
            }

            if ($missingColumns['dining_charge']) {
                $table->decimal('dining_charge', 10, 2)->default(0);
            }

            if ($missingColumns['dining_settings']) {
                $table->json('dining_settings')->nullable();
            }

            if ($missingColumns['weekly_timings']) {
                $table->json('weekly_timings')->nullable();
            }

            if ($missingColumns['timezone']) {
                $table->string('timezone')->default('Asia/Kolkata');
            }

            if ($missingColumns['auto_accept_orders']) {
                $table->boolean('auto_accept_orders')->default(false);
            }

            if ($missingColumns['order_lead_time']) {
                $table->integer('order_lead_time')->default(0);
            }

            if ($missingColumns['same_day_delivery']) {
                $table->boolean('same_day_delivery')->default(true);
            }
        });
    }

    public function down(): void
    {
        // Intentionally left non-destructive.
        // This migration repairs older databases that missed onboarding columns.
    }
};
