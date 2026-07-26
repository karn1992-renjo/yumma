<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_charge_settings', 'order_acceptance_timeout_seconds')) {
                $table->unsignedSmallInteger('order_acceptance_timeout_seconds')
                    ->default(180)
                    ->after('platform_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_charge_settings', 'order_acceptance_timeout_seconds')) {
                $table->dropColumn('order_acceptance_timeout_seconds');
            }
        });
    }
};
