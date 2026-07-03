<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('delivery_areas')) {
            return;
        }

        Schema::table('delivery_areas', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_areas', 'area_type')) {
                $table->enum('area_type', ['circle', 'polygon'])->default('circle');
            }
            
            if (!Schema::hasColumn('delivery_areas', 'polygon_coordinates')) {
                $table->json('polygon_coordinates')->nullable();
            }
            
            if (!Schema::hasColumn('delivery_areas', 'deleted_at')) {
                $table->softDeletes();
            }
            
            // For SQLite, we need to recreate the table to make columns nullable
            // Instead, we'll just add new columns and keep old ones as is
        });
    }
    
    public function down()
    {
        if (!Schema::hasTable('delivery_areas')) {
            return;
        }

        Schema::table('delivery_areas', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_areas', 'area_type')) {
                $table->dropColumn('area_type');
            }
            if (Schema::hasColumn('delivery_areas', 'polygon_coordinates')) {
                $table->dropColumn('polygon_coordinates');
            }
            if (Schema::hasColumn('delivery_areas', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
