<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each step guarded separately: MySQL auto-commits DDL even inside
        // Laravel's migration "transaction", so a prior partial failure on
        // this same migration (confirmed live -- "Duplicate column name
        // 'parent_id'" on a plain re-run) can leave the column present
        // without the FK/index ever having been added, or vice versa.
        if (! Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('restaurant_id');
            });
        }

        if (! Schema::hasIndex('categories', 'categories_parent_id_foreign')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('categories')->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('categories', 'categories_restaurant_id_parent_id_index')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['restaurant_id', 'parent_id'], 'categories_restaurant_id_parent_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
