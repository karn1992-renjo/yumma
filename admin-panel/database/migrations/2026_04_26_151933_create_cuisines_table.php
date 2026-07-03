<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('cuisines')) {
            Schema::create('cuisines', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('display_order')->default(0);
                $table->boolean('popular')->default(false);
                $table->timestamps();
            });
        }
        
        // Insert default cuisines
        $defaultCuisines = [
            ['name' => 'North Indian', 'icon' => 'fas fa-utensils', 'display_order' => 1, 'popular' => true],
            ['name' => 'South Indian', 'icon' => 'fas fa-utensil-spoon', 'display_order' => 2, 'popular' => true],
            ['name' => 'Chinese', 'icon' => 'fas fa-drumstick-bite', 'display_order' => 3, 'popular' => true],
            ['name' => 'Italian', 'icon' => 'fas fa-pizza-slice', 'display_order' => 4, 'popular' => true],
            ['name' => 'Continental', 'icon' => 'fas fa-egg', 'display_order' => 5, 'popular' => false],
            ['name' => 'Street Food', 'icon' => 'fas fa-hotdog', 'display_order' => 6, 'popular' => true],
            ['name' => 'Biryani', 'icon' => 'fas fa-bowl-food', 'display_order' => 7, 'popular' => true],
            ['name' => 'Seafood', 'icon' => 'fas fa-fish', 'display_order' => 8, 'popular' => false],
            ['name' => 'Desserts', 'icon' => 'fas fa-ice-cream', 'display_order' => 9, 'popular' => false],
            ['name' => 'Beverages', 'icon' => 'fas fa-mug-hot', 'display_order' => 10, 'popular' => false],
        ];
        
        foreach ($defaultCuisines as $cuisine) {
            if (!DB::table('cuisines')->where('name', $cuisine['name'])->exists()) {
                DB::table('cuisines')->insert([
                    'name' => $cuisine['name'],
                    'slug' => \Illuminate\Support\Str::slug($cuisine['name']),
                    'icon' => $cuisine['icon'],
                    'display_order' => $cuisine['display_order'],
                    'popular' => $cuisine['popular'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('cuisines');
    }
};