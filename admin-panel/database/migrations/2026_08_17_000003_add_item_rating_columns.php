<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('special_instructions');
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'total_ratings')) {
                $table->unsignedInteger('total_ratings')->default(0)->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'rating')) {
                $table->dropColumn('rating');
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'total_ratings')) {
                $table->dropColumn('total_ratings');
            }
        });
    }
};
