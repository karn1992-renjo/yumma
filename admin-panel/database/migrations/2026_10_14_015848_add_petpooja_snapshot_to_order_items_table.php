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
        Schema::table('order_items', function (Blueprint $table) {
            // Captured at order-creation time from the matched menu_items
            // row's petpooja_item_id/petpooja_variation_id/petpooja_meta,
            // plus resolved addon selections, so the Save Order payload
            // builder never has to re-derive from a menu that may have
            // since changed.
            $table->json('petpooja_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('petpooja_snapshot');
        });
    }
};
