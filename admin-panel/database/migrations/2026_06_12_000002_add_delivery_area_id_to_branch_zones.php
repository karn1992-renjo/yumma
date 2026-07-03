<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_zones', function (Blueprint $table) {
            if (! Schema::hasColumn('branch_zones', 'delivery_area_id')) {
                $table->foreignId('delivery_area_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('delivery_areas')
                    ->nullOnDelete();
                $table->unique('delivery_area_id', 'branch_zones_delivery_area_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_zones', function (Blueprint $table) {
            if (Schema::hasColumn('branch_zones', 'delivery_area_id')) {
                $table->dropUnique('branch_zones_delivery_area_unique');
                $table->dropConstrainedForeignId('delivery_area_id');
            }
        });
    }
};
