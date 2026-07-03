<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'restaurant_earning')) {
                $table->decimal('restaurant_earning', 12, 2)->nullable()->after('total');
            }
            if (!Schema::hasColumn('orders', 'driver_earning')) {
                $table->decimal('driver_earning', 12, 2)->nullable()->after('restaurant_earning');
            }
            if (!Schema::hasColumn('orders', 'admin_commission')) {
                $table->decimal('admin_commission', 12, 2)->nullable()->after('driver_earning');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['restaurant_earning', 'driver_earning', 'admin_commission']);
        });
    }
};