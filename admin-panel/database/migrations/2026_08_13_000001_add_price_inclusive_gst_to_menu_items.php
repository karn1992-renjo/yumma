<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'is_price_inclusive_gst')) {
                $table->boolean('is_price_inclusive_gst')->default(false)->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'is_price_inclusive_gst')) {
                $table->dropColumn('is_price_inclusive_gst');
            }
        });
    }
};
