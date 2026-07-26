<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_scratch_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_usage_id')->nullable()->constrained('promotion_usage')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->default('Scratch & Win');
            $table->string('status')->default('issued')->index();
            $table->json('reward_pool')->nullable();
            $table->json('reward')->nullable();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('revealed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['promotion_id', 'order_id', 'user_id'], 'scratch_unique_order_promotion_user');
            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_scratch_cards');
    }
};
