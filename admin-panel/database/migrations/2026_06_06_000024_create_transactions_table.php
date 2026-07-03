<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('type', 32);
                $table->string('status', 32);
                $table->string('razorpay_id')->nullable();
                $table->string('transaction_id')->unique();
                $table->string('payment_method')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index('order_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
