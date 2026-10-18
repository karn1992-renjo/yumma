<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments the gateway confirmed as charged, but where order creation
     * then failed even after retries (see
     * PaymentController::createOrderFromVerifiedCheckout()) -- persisted
     * here (not just logged) so an admin can resolve them from a UI any
     * time later, since the 20-minute checkout cache the original attempt
     * relied on will long since have expired.
     */
    public function up(): void
    {
        Schema::create('payment_recovery_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('payment_method', 32); // razorpay, cashfree, stripe
            $table->string('payment_id')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('checkout_payload'); // the order payload to replay
            $table->json('verification_payload')->nullable(); // signature/etc
            $table->string('status', 24)->default('pending'); // pending, resolved, not_paid, failed
            $table->unsignedBigInteger('resolved_order_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_recovery_queue');
    }
};
