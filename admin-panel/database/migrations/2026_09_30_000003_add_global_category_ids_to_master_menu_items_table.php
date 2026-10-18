<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded separately: see 2026_09_30_000001_add_parent_id_to_categories_table
        // for why a prior partial failure on this same migration can leave a
        // column present without its FK ever having been added.
        if (! Schema::hasColumn('master_menu_items', 'global_category_id')) {
            Schema::table('master_menu_items', function (Blueprint $table) {
                $table->foreignId('global_category_id')->nullable()->after('cuisine_id');
            });
        }

        if (! Schema::hasIndex('master_menu_items', 'master_menu_items_global_category_id_foreign')) {
            Schema::table('master_menu_items', function (Blueprint $table) {
                $table->foreign('global_category_id')->references('id')->on('global_menu_categories')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('master_menu_items', 'global_subcategory_id')) {
            Schema::table('master_menu_items', function (Blueprint $table) {
                $table->foreignId('global_subcategory_id')->nullable()->after('global_category_id');
            });
        }

        if (! Schema::hasIndex('master_menu_items', 'master_menu_items_global_subcategory_id_foreign')) {
            Schema::table('master_menu_items', function (Blueprint $table) {
                $table->foreign('global_subcategory_id')->references('id')->on('global_menu_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('master_menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('global_category_id');
            $table->dropConstrainedForeignId('global_subcategory_id');
        });
    }
};
