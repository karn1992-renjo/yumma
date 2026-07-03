<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('rating')->unsigned()->comment('1-5 stars');
            $table->text('comment')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['restaurant_id', 'status']);
            $table->index(['user_id', 'restaurant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
};