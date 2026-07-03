<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_payment_mode')) {
                $table->string('delivery_payment_mode')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'cash_collected_amount')) {
                $table->decimal('cash_collected_amount', 12, 2)->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'cash_collected_at')) {
                $table->timestamp('cash_collected_at')->nullable()->after('cash_collected_amount');
            }
            if (!Schema::hasColumn('orders', 'online_payment_verified_at')) {
                $table->timestamp('online_payment_verified_at')->nullable()->after('cash_collected_at');
            }
            if (!Schema::hasColumn('orders', 'payment_gateway_fee')) {
                $table->decimal('payment_gateway_fee', 12, 2)->nullable()->after('admin_commission');
            }
            if (!Schema::hasColumn('orders', 'gst_on_commission')) {
                $table->decimal('gst_on_commission', 12, 2)->nullable()->after('payment_gateway_fee');
            }
            if (!Schema::hasColumn('orders', 'platform_commission')) {
                $table->decimal('platform_commission', 12, 2)->nullable()->after('gst_on_commission');
            }
            if (!Schema::hasColumn('orders', 'payout_processed')) {
                $table->boolean('payout_processed')->default(false)->after('platform_commission');
            }
            if (!Schema::hasColumn('orders', 'payout_processed_at')) {
                $table->timestamp('payout_processed_at')->nullable()->after('payout_processed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'delivery_payment_mode',
                'cash_collected_amount',
                'cash_collected_at',
                'online_payment_verified_at',
                'payment_gateway_fee',
                'gst_on_commission',
                'platform_commission',
                'payout_processed',
                'payout_processed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
