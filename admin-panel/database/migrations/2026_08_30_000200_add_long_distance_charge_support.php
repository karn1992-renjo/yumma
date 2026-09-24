<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Long-distance restaurant charge: when a delivery order is placed beyond the
 * admin-configured free radius, the restaurant is charged a fee (per-km on the
 * excess, or a flat amount). The fee is deducted from the restaurant's earning
 * at payout time; the customer is not charged.
 *
 * Config lives in AppSetting (long_distance_charge_*), edited under
 * admin Settings -> Delivery Radius.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_distance_km')) {
                $table->decimal('delivery_distance_km', 10, 3)->nullable()->after('night_surcharge');
            }
            if (! Schema::hasColumn('orders', 'long_distance_charge')) {
                $table->decimal('long_distance_charge', 10, 2)->default(0)->after('delivery_distance_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['long_distance_charge', 'delivery_distance_km'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
