<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'layout_mode')) {
                $table->string('layout_mode', 32)->default('text_image')->after('banner_type');
            }

            if (!Schema::hasColumn('banners', 'image_ratio')) {
                $table->unsignedTinyInteger('image_ratio')->default(46)->after('layout_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (Schema::hasColumn('banners', 'image_ratio')) {
                $table->dropColumn('image_ratio');
            }

            if (Schema::hasColumn('banners', 'layout_mode')) {
                $table->dropColumn('layout_mode');
            }
        });
    }
};
