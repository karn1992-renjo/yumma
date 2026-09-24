<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_ai_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->index();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role', 16); // user | assistant
            $table->text('content');
            $table->json('plan')->nullable();
            $table->json('suggestions')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ai_messages');
    }
};
