<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('driver_delivery_base', 12, 2)->default(0)->after('driver_earning');
            $table->decimal('admin_delivery_commission', 12, 2)->default(0)->after('admin_commission');
            $table->string('admin_delivery_commission_type')->nullable()->after('admin_delivery_commission');
            $table->decimal('admin_delivery_commission_value', 12, 2)->nullable()->after('admin_delivery_commission_type');
            $table->decimal('driver_deduction', 12, 2)->default(0)->after('admin_delivery_commission_value');
            $table->string('driver_deduction_type')->nullable()->after('driver_deduction');
            $table->decimal('driver_deduction_value', 12, 2)->nullable()->after('driver_deduction_type');
            $table->decimal('batch_bonus', 12, 2)->default(0)->after('driver_deduction_value');
            $table->string('restaurant_commission_type')->nullable()->after('platform_commission');
            $table->decimal('restaurant_commission_value', 12, 2)->nullable()->after('restaurant_commission_type');
            $table->foreignId('restaurant_payout_id')->nullable()->after('payout_processed_at')->constrained('payouts')->nullOnDelete();
            $table->foreignId('driver_payout_id')->nullable()->after('restaurant_payout_id')->constrained('payouts')->nullOnDelete();
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->decimal('gst_on_commission', 12, 2)->default(0)->after('platform_commission');
            $table->decimal('payment_gateway_fee', 12, 2)->default(0)->after('gst_on_commission');
            $table->decimal('admin_delivery_commission', 12, 2)->default(0)->after('delivery_fee');
            $table->decimal('driver_deduction', 12, 2)->default(0)->after('admin_delivery_commission');
            $table->decimal('batch_bonus', 12, 2)->default(0)->after('driver_deduction');
            $table->json('order_ids')->nullable()->after('batch_bonus');
            $table->json('breakdown')->nullable()->after('order_ids');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_payout_id');
            $table->dropConstrainedForeignId('restaurant_payout_id');
            $table->dropColumn([
                'driver_delivery_base',
                'admin_delivery_commission',
                'admin_delivery_commission_type',
                'admin_delivery_commission_value',
                'driver_deduction',
                'driver_deduction_type',
                'driver_deduction_value',
                'batch_bonus',
                'restaurant_commission_type',
                'restaurant_commission_value',
            ]);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'gst_on_commission',
                'payment_gateway_fee',
                'admin_delivery_commission',
                'driver_deduction',
                'batch_bonus',
                'order_ids',
                'breakdown',
            ]);
        });
    }
};
