<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('restrict');
            // Plain string, not enum -- same rationale as
            // restaurant_subscriptions.status (see that migration). Valid
            // values: pending_authorization, trialing, active, past_due,
            // expired, cancelled. Unlike a restaurant subscription, a lapsed
            // Gold membership has no "billing_choice_required" state -- it
            // simply stops waiving delivery fees until renewed.
            $table->string('status', 30)->default('pending_authorization');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->enum('gateway', ['razorpay', 'stripe', 'cashfree'])->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_payment_method_label')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
