<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'food_type')) {
                $table->string('food_type', 20)->default('veg')->after('is_veg');
            }
        });

        Schema::table('partner_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_applications', 'is_pure_veg')) {
                $table->boolean('is_pure_veg')->default(false)->after('cuisine');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            if (Schema::hasColumn('partner_applications', 'is_pure_veg')) {
                $table->dropColumn('is_pure_veg');
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'food_type')) {
                $table->dropColumn('food_type');
            }
        });
    }
};
