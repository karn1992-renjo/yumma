<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('source', 32);
            $table->string('status', 32)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('INR');
            $table->string('transaction_id')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('payment_link_id')->nullable();
            $table->text('payment_link')->nullable();
            $table->text('qr_reference')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('webhook_event_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->text('collection_notes')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['gateway', 'gateway_reference']);
            $table->index('payment_link_id');
            $table->unique('idempotency_key');
            $table->unique('webhook_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
