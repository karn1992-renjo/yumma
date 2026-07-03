<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_staff')) {
            return;
        }

        Schema::create('restaurant_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('role')->default('staff');
            $table->string('shift')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
            $table->index(['restaurant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_staff');
    }
};
