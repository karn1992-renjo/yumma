<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_charge_settings', 'free_delivery_days')) {
                $table->json('free_delivery_days')->nullable()->after('free_delivery_global');
            }

            if (! Schema::hasColumn('delivery_charge_settings', 'free_delivery_area_ids')) {
                $table->json('free_delivery_area_ids')->nullable()->after('free_delivery_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_charge_settings', 'free_delivery_area_ids')) {
                $table->dropColumn('free_delivery_area_ids');
            }

            if (Schema::hasColumn('delivery_charge_settings', 'free_delivery_days')) {
                $table->dropColumn('free_delivery_days');
            }
        });
    }
};
