<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('delivery_areas')) {
            return;
        }

        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('area_type', ['circle', 'polygon'])->default('circle');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('radius_km', 5, 2)->nullable()->default(10);
            $table->json('polygon_coordinates')->nullable();
            $table->integer('max_daily_bookings')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_areas');
    }
};
