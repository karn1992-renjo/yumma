<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('dining_bookings', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'success', 'failed', 'refunded'])
                    ->default('pending')
                    ->after('booking_charge');
            }

            if (!Schema::hasColumn('dining_bookings', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }

            if (!Schema::hasColumn('dining_bookings', 'payment_id')) {
                $table->string('payment_id')->nullable()->after('payment_method');
            }

            if (!Schema::hasColumn('dining_bookings', 'gateway_order_id')) {
                $table->string('gateway_order_id')->nullable()->after('payment_id');
            }

            if (!Schema::hasColumn('dining_bookings', 'online_payment_verified_at')) {
                $table->timestamp('online_payment_verified_at')->nullable()->after('gateway_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dining_bookings', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'online_payment_verified_at',
                'gateway_order_id',
                'payment_id',
                'payment_method',
                'payment_status',
            ], fn ($column) => Schema::hasColumn('dining_bookings', $column)));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
