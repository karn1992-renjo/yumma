<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle', 500)->nullable();
            $table->string('section_type');
            $table->string('data_source')->default('auto');
            $table->json('configuration')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'display_order']);
            $table->index(['section_type', 'data_source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
