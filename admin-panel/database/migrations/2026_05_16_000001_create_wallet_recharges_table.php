<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallet_recharges')) {
            Schema::create('wallet_recharges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('INR');
                $table->string('status', 32)->default('pending');
                $table->string('payment_method', 32);
                $table->string('gateway_order_id')->nullable();
                $table->string('gateway_payment_id')->nullable();
                $table->string('gateway_signature')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('payment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_recharges');
    }
};
