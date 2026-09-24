<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ai_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tool', 64);
            $table->json('args')->nullable();
            $table->text('summary')->nullable();
            $table->string('risk', 16)->default('low');
            $table->string('status', 16)->default('proposed'); // proposed|executed|failed|cancelled
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ai_actions');
    }
};
