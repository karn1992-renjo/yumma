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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('petpooja_item_id')->nullable()->index();
            // Set only when this row represents one Petpooja variation --
            // our cart has no native "one product, many variants" concept,
            // so each variation gets materialized as its own menu_items row.
            $table->string('petpooja_variation_id')->nullable()->index();
            // Raw item_tax/gst_type/tax_inclusive/gst_liability/combo_items/
            // nutrition/item_tags -- whatever `variants`/`add_ons` don't
            // already cover.
            $table->json('petpooja_meta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['petpooja_item_id', 'petpooja_variation_id', 'petpooja_meta']);
        });
    }
};
