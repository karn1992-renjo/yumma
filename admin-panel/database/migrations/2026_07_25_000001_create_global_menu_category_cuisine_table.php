<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('global_menu_category_cuisine')) {
            return;
        }

        Schema::create('global_menu_category_cuisine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_menu_category_id')
                ->constrained('global_menu_categories')
                ->cascadeOnDelete();
            $table->foreignId('cuisine_id')
                ->constrained('cuisines')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['global_menu_category_id', 'cuisine_id'], 'global_category_cuisine_unique');
            $table->index('cuisine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_menu_category_cuisine');
    }
};
