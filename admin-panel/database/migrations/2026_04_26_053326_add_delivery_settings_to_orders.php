// database/migrations/2026_01_01_000013_add_delivery_settings_to_orders.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_otp')->nullable()->after('delivery_lng');
            $table->boolean('otp_verified')->default(false)->after('delivery_otp');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_verified');
            $table->string('return_status')->nullable()->after('otp_verified_at'); // requested, approved, rejected, completed
            $table->string('return_reason')->nullable()->after('return_status');
            $table->decimal('return_amount', 10, 2)->nullable()->after('return_reason');
            $table->timestamp('return_processed_at')->nullable()->after('return_amount');
            $table->string('order_processing_type')->default('after_restaurant_accept')->after('return_processed_at'); // after_restaurant_accept, only_if_driver_available
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_otp', 'otp_verified', 'otp_verified_at',
                'return_status', 'return_reason', 'return_amount',
                'return_processed_at', 'order_processing_type'
            ]);
        });
    }
};