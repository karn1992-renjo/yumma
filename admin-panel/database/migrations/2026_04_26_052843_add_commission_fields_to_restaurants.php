// database/migrations/2026_01_01_000001_add_commission_fields_to_restaurants.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(15.00)->after('delivery_fee');
            $table->decimal('platform_fee', 10, 2)->default(10.00)->after('commission_rate');
            $table->string('restaurant_type')->default('delivery')->after('platform_fee'); // delivery, dining, both
            $table->time('open_time')->nullable()->after('restaurant_type');
            $table->time('close_time')->nullable()->after('open_time');
            $table->json('offline_reason')->nullable()->after('close_time');
            $table->decimal('cancellation_rate', 5, 2)->default(0)->after('offline_reason');
            $table->integer('cancelled_orders_count')->default(0)->after('cancellation_rate');
            $table->boolean('auto_accept_orders')->default(false)->after('cancelled_orders_count');
            $table->string('printer_type')->nullable()->after('auto_accept_orders');
            $table->string('printer_ip')->nullable()->after('printer_type');
        });
    }

    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'commission_rate', 'platform_fee', 'restaurant_type',
                'open_time', 'close_time', 'offline_reason',
                'cancellation_rate', 'cancelled_orders_count',
                'auto_accept_orders', 'printer_type', 'printer_ip'
            ]);
        });
    }
};