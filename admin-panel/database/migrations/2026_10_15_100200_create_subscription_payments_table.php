<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            // Exactly one of these two is set -- a restaurant subscription
            // payment or a customer Gold subscription payment. Both nullable
            // (rather than a polymorphic pair) since there are only ever
            // these two subscribable entities and this keeps the FKs typed.
            $table->foreignId('restaurant_subscription_id')->nullable()->constrained()->onDelete('cascade');
            // No ->constrained() here -- customer_subscriptions is created by
            // a LATER migration (2026_10_15_100400), so an FK constraint at
            // this point would fail. The relation still works fine without a
            // DB-level constraint; application code (GoldSubscriptionService)
            // is the only writer.
            $table->unsignedBigInteger('customer_subscription_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('gateway')->nullable();
            $table->string('gateway_charge_id')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['restaurant_subscription_id', 'status']);
            $table->index(['customer_subscription_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_payments');
    }
};
