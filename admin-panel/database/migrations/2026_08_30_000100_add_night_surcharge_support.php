<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide late-night delivery surcharge. The amount + window live in
 * AppSetting (night_surcharge_*), configured under admin Settings. The charge
 * is self-funding: what the customer pays becomes a driver night bonus, the
 * same way the zone surge fee works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'night_surcharge')) {
                $table->decimal('night_surcharge', 10, 2)->default(0)->after('surge_fee');
            }
        });

        Schema::table('gig_incentives', function (Blueprint $table) {
            if (! Schema::hasColumn('gig_incentives', 'night_surcharge_bonus')) {
                $table->decimal('night_surcharge_bonus', 10, 2)->default(0)->after('zone_surge_bonus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'night_surcharge')) {
                $table->dropColumn('night_surcharge');
            }
        });

        Schema::table('gig_incentives', function (Blueprint $table) {
            if (Schema::hasColumn('gig_incentives', 'night_surcharge_bonus')) {
                $table->dropColumn('night_surcharge_bonus');
            }
        });
    }
};
