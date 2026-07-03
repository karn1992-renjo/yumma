<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            foreach (['variants', 'add_ons'] as $column) {
                if (!Schema::hasColumn('menu_items', $column)) {
                    $table->json($column)->nullable()->after('tags');
                }
            }

            foreach (['is_bestseller', 'is_new', 'is_spicy', 'is_combo'] as $column) {
                if (!Schema::hasColumn('menu_items', $column)) {
                    $table->boolean($column)->default(false)->after('is_recommended');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'variants',
                'add_ons',
                'is_bestseller',
                'is_new',
                'is_spicy',
                'is_combo',
            ], fn ($column) => Schema::hasColumn('menu_items', $column)));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
