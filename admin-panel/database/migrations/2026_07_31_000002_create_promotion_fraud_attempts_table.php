<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_fraud_attempts')) {
            Schema::create('promotion_fraud_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('coupon_code')->nullable()->index();
                $table->string('rule_key', 80)->nullable()->index();
                $table->string('reason_code', 120)->index();
                $table->string('reason', 255);
                $table->string('device_id')->nullable()->index();
                $table->string('address_hash')->nullable()->index();
                $table->string('payment_instrument_hash')->nullable()->index();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['promotion_id', 'created_at']);
                $table->index(['restaurant_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_fraud_attempts');
    }
};