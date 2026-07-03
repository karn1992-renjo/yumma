<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'customer_fcm_token')) {
                $table->string('customer_fcm_token', 512)->nullable()->after('fcm_token');
            }

            if (! Schema::hasColumn('users', 'restaurant_fcm_token')) {
                $table->string('restaurant_fcm_token', 512)->nullable()->after('customer_fcm_token');
            }

            if (! Schema::hasColumn('users', 'driver_fcm_token')) {
                $table->string('driver_fcm_token', 512)->nullable()->after('restaurant_fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['driver_fcm_token', 'restaurant_fcm_token', 'customer_fcm_token'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
