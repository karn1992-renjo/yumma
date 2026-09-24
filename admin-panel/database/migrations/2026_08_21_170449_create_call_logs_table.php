<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_call_mapping_id')->nullable()->constrained('order_call_mappings')->nullOnDelete();
            $table->enum('direction', ['dial_in', 'click_to_call']);
            $table->enum('initiator_role', ['customer', 'restaurant', 'driver', 'system']);
            $table->foreignId('initiator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('leg_from', ['customer', 'restaurant', 'driver']);
            $table->enum('leg_to', ['customer', 'restaurant', 'driver']);
            $table->string('exotel_call_sid')->nullable();
            $table->string('exophone', 32)->nullable();
            $table->enum('provider_used', ['exotel', 'raw_fallback'])->default('exotel');
            $table->string('status', 32)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('recording_url', 512)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('raw_webhook_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['status']);
            $table->index(['exotel_call_sid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
