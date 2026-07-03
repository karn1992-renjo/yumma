<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('commission_settings')) {
            Schema::create('commission_settings', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type'); // admin, restaurant, delivery_partner
                $table->decimal('rate', 5, 2);
                $table->string('calculation_type')->default('percentage');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        
        // Insert default commission settings
        DB::table('commission_settings')->insert([
            [
                'name' => 'Admin Commission',
                'type' => 'admin',
                'rate' => 15.00,
                'calculation_type' => 'percentage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Restaurant Commission',
                'type' => 'restaurant',
                'rate' => 15.00,
                'calculation_type' => 'percentage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Delivery Partner Commission',
                'type' => 'delivery_partner',
                'rate' => 5.00,
                'calculation_type' => 'percentage',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('commission_settings');
    }
};