<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'delivery_area_id')) {
                $table->foreignId('delivery_area_id')
                    ->nullable()
                    ->after('longitude')
                    ->constrained('delivery_areas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'delivery_area_id')) {
                $table->dropConstrainedForeignId('delivery_area_id');
            }
        });
    }
};
