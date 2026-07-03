<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'redirect_type')) {
                $table->string('redirect_type', 32)->nullable()->after('link');
            }
            if (!Schema::hasColumn('banners', 'redirect_category_id')) {
                $table->foreignId('redirect_category_id')->nullable()->after('redirect_type')->constrained('categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('banners', 'redirect_restaurant_id')) {
                $table->foreignId('redirect_restaurant_id')->nullable()->after('redirect_category_id')->constrained('restaurants')->nullOnDelete();
            }
            if (!Schema::hasColumn('banners', 'redirect_menu_item_id')) {
                $table->foreignId('redirect_menu_item_id')->nullable()->after('redirect_restaurant_id')->constrained('menu_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            foreach (['redirect_menu_item_id', 'redirect_restaurant_id', 'redirect_category_id'] as $column) {
                if (Schema::hasColumn('banners', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            if (Schema::hasColumn('banners', 'redirect_type')) {
                $table->dropColumn('redirect_type');
            }
        });
    }
};
