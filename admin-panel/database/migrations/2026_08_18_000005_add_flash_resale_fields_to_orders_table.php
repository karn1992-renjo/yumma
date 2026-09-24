<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'resale_status')) {
                $table->string('resale_status')->nullable()->after('delivery_failure_reason');
            }
            if (!Schema::hasColumn('orders', 'resale_price')) {
                $table->decimal('resale_price', 12, 2)->nullable()->after('resale_status');
            }
            if (!Schema::hasColumn('orders', 'resale_offer_expires_at')) {
                $table->timestamp('resale_offer_expires_at')->nullable()->after('resale_price');
            }
            if (!Schema::hasColumn('orders', 'resale_claimed_by')) {
                $table->unsignedBigInteger('resale_claimed_by')->nullable()->after('resale_offer_expires_at');
            }
            if (!Schema::hasColumn('orders', 'food_returned_at')) {
                $table->timestamp('food_returned_at')->nullable()->after('resale_claimed_by');
            }
            if (!Schema::hasColumn('orders', 'original_order_id')) {
                $table->unsignedBigInteger('original_order_id')->nullable()->after('food_returned_at')->index();
            }
            if (!Schema::hasColumn('orders', 'driver_earning_processed_at')) {
                $table->timestamp('driver_earning_processed_at')->nullable()->after('original_order_id');
            }
            if (!Schema::hasColumn('orders', 'restaurant_earning_processed_at')) {
                $table->timestamp('restaurant_earning_processed_at')->nullable()->after('driver_earning_processed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_filter([
                'resale_status',
                'resale_price',
                'resale_offer_expires_at',
                'resale_claimed_by',
                'food_returned_at',
                'original_order_id',
                'driver_earning_processed_at',
                'restaurant_earning_processed_at',
            ], fn ($column) => Schema::hasColumn('orders', $column));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
