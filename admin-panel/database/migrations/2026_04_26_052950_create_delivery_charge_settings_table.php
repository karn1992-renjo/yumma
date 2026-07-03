<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_charge_settings', function (Blueprint $table) {
            $table->id();
            $table->string('charge_type')->default('fixed'); // fixed, per_km
            $table->decimal('base_charge', 10, 2)->default(40.00);
            $table->decimal('per_km_charge', 10, 2)->default(10.00);
            $table->integer('free_delivery_threshold')->nullable(); // order amount for free delivery
            $table->boolean('free_delivery_global')->default(false);
            $table->decimal('admin_contribution_percent', 5, 2)->default(50.00);
            $table->decimal('restaurant_contribution_percent', 5, 2)->default(50.00);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_charge_settings');
    }
};