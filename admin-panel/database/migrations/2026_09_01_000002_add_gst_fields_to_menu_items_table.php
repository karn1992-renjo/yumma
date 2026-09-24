<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'gst_rate')) {
                // Per-item GST %. Fallback chain when null:
                // MasterMenuItem.gst -> business default rate.
                $table->decimal('gst_rate', 5, 2)->nullable()->after('is_price_inclusive_gst');
            }
            if (! Schema::hasColumn('menu_items', 'hsn_code')) {
                $table->string('hsn_code', 20)->nullable()->after('gst_rate');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) {
            foreach (['gst_rate', 'hsn_code'] as $column) {
                if (Schema::hasColumn('menu_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
