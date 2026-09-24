<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $add = function (string $name, string $type = 'decimal') use ($table) {
                    if (Schema::hasColumn('orders', $name)) {
                        return;
                    }
                    $type === 'string'
                        ? $table->string($name, 12)->nullable()
                        : $table->decimal($name, 12, 2)->nullable();
                };
                // Sec 9(5): 5% GST on food -- collected from the customer, paid
                // by the platform (ECO), never reaches the restaurant.
                $add('eco_gst_food');
                $add('eco_gst_food_cgst');
                $add('eco_gst_food_sgst');
                // 18% GST on the platform's own delivery / platform fees.
                $add('service_gst');
                $add('service_gst_cgst');
                $add('service_gst_sgst');
                // Sec 52 GST TCS on non-9(5) restaurant-liable supplies.
                $add('tcs_cgst');
                $add('tcs_sgst');
                // Income-tax TDS deducted at settlement.
                $add('tds_194o_amount');
                $add('tds_194c_amount');
                $add('tds_194o_rate');
                $add('tds_194c_rate');
            });
        }

        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                if (! Schema::hasColumn('payouts', 'pre_tax_amount')) {
                    $table->decimal('pre_tax_amount', 14, 2)->nullable()->after('net_amount');
                }
                if (! Schema::hasColumn('payouts', 'tds_amount')) {
                    $table->decimal('tds_amount', 12, 2)->nullable()->after('pre_tax_amount');
                }
                if (! Schema::hasColumn('payouts', 'tds_section')) {
                    $table->string('tds_section', 12)->nullable()->after('tds_amount');
                }
                if (! Schema::hasColumn('payouts', 'tcs_amount')) {
                    $table->decimal('tcs_amount', 12, 2)->nullable()->after('tds_section');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'eco_gst_food', 'eco_gst_food_cgst', 'eco_gst_food_sgst',
                    'service_gst', 'service_gst_cgst', 'service_gst_sgst',
                    'tcs_cgst', 'tcs_sgst', 'tds_194o_amount', 'tds_194c_amount',
                    'tds_194o_rate', 'tds_194c_rate',
                ] as $c) {
                    if (Schema::hasColumn('orders', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                foreach (['pre_tax_amount', 'tds_amount', 'tds_section', 'tcs_amount'] as $c) {
                    if (Schema::hasColumn('payouts', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
