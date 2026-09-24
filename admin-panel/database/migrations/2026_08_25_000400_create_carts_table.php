<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carts')) {
            return;
        }

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->json('items');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'restaurant_id']);
            $table->index(['last_activity_at', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
