<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'original_delivery_fee')) {
                $table->decimal('original_delivery_fee', 12, 2)->default(0)->after('delivery_fee');
            }
            if (! Schema::hasColumn('orders', 'delivery_discount')) {
                $table->decimal('delivery_discount', 12, 2)->default(0)->after('original_delivery_fee');
            }
            if (! Schema::hasColumn('orders', 'delivery_subsidy_source')) {
                $table->string('delivery_subsidy_source')->nullable()->after('delivery_discount');
            }
            if (! Schema::hasColumn('orders', 'admin_delivery_subsidy')) {
                $table->decimal('admin_delivery_subsidy', 12, 2)->default(0)->after('delivery_subsidy_source');
            }
            if (! Schema::hasColumn('orders', 'restaurant_delivery_subsidy')) {
                $table->decimal('restaurant_delivery_subsidy', 12, 2)->default(0)->after('admin_delivery_subsidy');
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'admin_delivery_subsidy')) {
                $table->decimal('admin_delivery_subsidy', 12, 2)->default(0)->after('delivery_fee');
            }
            if (! Schema::hasColumn('payouts', 'restaurant_delivery_subsidy')) {
                $table->decimal('restaurant_delivery_subsidy', 12, 2)->default(0)->after('admin_delivery_subsidy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('payouts', 'restaurant_delivery_subsidy') ? 'restaurant_delivery_subsidy' : null,
                Schema::hasColumn('payouts', 'admin_delivery_subsidy') ? 'admin_delivery_subsidy' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'restaurant_delivery_subsidy') ? 'restaurant_delivery_subsidy' : null,
                Schema::hasColumn('orders', 'admin_delivery_subsidy') ? 'admin_delivery_subsidy' : null,
                Schema::hasColumn('orders', 'delivery_subsidy_source') ? 'delivery_subsidy_source' : null,
                Schema::hasColumn('orders', 'delivery_discount') ? 'delivery_discount' : null,
                Schema::hasColumn('orders', 'original_delivery_fee') ? 'original_delivery_fee' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
