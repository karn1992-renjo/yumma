<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded separately: see 2026_09_30_000001_add_parent_id_to_categories_table
        // for why a prior partial failure on this same migration can leave
        // the column present without the FK ever having been added.
        if (! Schema::hasColumn('menu_items', 'subcategory_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->foreignId('subcategory_id')->nullable()->after('category_id');
            });
        }

        if (! Schema::hasIndex('menu_items', 'menu_items_subcategory_id_foreign')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->foreign('subcategory_id')->references('id')->on('categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
        });
    }
};
