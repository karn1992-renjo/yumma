<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Who actually cancelled this order -- customer, restaurant,
            // branch, admin, or system (an automatic/background decision
            // with no human actor, e.g. no driver was available). Separate
            // from the existing cancellation_reason text field.
            $table->string('cancelled_by_role', 20)->nullable()->after('cancellation_reason');
            $table->string('cancelled_by_name', 150)->nullable()->after('cancelled_by_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by_role', 'cancelled_by_name']);
        });
    }
};
