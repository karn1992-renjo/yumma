<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the customer add / update delivery instructions and cooking requests
 * after the order is placed (order-tracking screen). Cooking requests reuse
 * the existing `special_instructions`; delivery instructions get their own
 * column so they can be shown to the driver separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_instructions')) {
                $table->string('delivery_instructions', 500)->nullable()->after('special_instructions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_instructions')) {
                $table->dropColumn('delivery_instructions');
            }
        });
    }
};
