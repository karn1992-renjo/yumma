<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('restrict');
            $table->enum('scope', ['full_plan', 'stories_addon'])->default('full_plan');
            // Deliberately a plain string, not an enum (matches this codebase's
            // established convention for frequently-evolving status columns,
            // e.g. menu_items.item_source) -- avoids an ALTER TABLE ... MODIFY
            // COLUMN for every future status value. Valid values: pending_authorization
            // (gateway subscription created, customer hasn't completed the mandate
            // checkout yet), trialing, active, past_due, billing_choice_required,
            // expired, cancelled.
            $table->string('status', 30)->default('pending_authorization');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->unsignedInteger('orders_used_in_period')->default(0);
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('gateway', ['razorpay', 'stripe', 'cashfree'])->nullable();
            // Tokenized gateway references only -- never raw card data.
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_payment_method_label')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'scope', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('restaurant_subscriptions');
    }
};
