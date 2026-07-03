<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_charge_settings', 'platform_fee')) {
                $table->decimal('platform_fee', 10, 2)->default(0)->after('per_km_charge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_charge_settings', 'platform_fee')) {
                $table->dropColumn('platform_fee');
            }
        });
    }
};
