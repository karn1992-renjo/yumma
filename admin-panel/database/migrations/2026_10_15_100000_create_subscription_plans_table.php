<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('audience', ['restaurant', 'customer'])->default('restaurant');
            $table->enum('scope', ['full_plan', 'stories_addon'])->default('full_plan');
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('billing_period', ['monthly', 'quarterly', 'yearly']);
            $table->decimal('price', 10, 2);
            // Optional "was" price shown struck through next to the real
            // price for a promotional display (e.g. a limited-time Gold
            // membership offer). Null = no promo, just show `price` plain.
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('order_limit')->nullable();
            $table->unsignedInteger('menu_item_limit')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->boolean('includes_stories')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            // Populated lazily on first purchase, one column per gateway since
            // a plan can be provisioned on more than one gateway independently
            // (a Razorpay plan id is meaningless to Cashfree and vice versa).
            $table->string('razorpay_plan_id')->nullable();
            $table->string('cashfree_plan_id')->nullable();
            $table->timestamps();

            $table->index(['audience', 'scope', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscription_plans');
    }
};
