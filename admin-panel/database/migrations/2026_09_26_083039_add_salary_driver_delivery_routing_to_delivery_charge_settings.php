<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_charge_settings', 'salary_driver_delivery_recipient')) {
                $table->string('salary_driver_delivery_recipient', 20)->default('branch')->after('restaurant_contribution_percent');
            }
            if (! Schema::hasColumn('delivery_charge_settings', 'salary_driver_branch_share_percent')) {
                $table->decimal('salary_driver_branch_share_percent', 5, 2)->default(70)->after('salary_driver_delivery_recipient');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_charge_settings', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_charge_settings', 'salary_driver_branch_share_percent')) {
                $table->dropColumn('salary_driver_branch_share_percent');
            }
            if (Schema::hasColumn('delivery_charge_settings', 'salary_driver_delivery_recipient')) {
                $table->dropColumn('salary_driver_delivery_recipient');
            }
        });
    }
};
