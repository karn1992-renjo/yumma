<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            // "recurring" = gateway mandate that charges every period; "prepaid" = pay as you go: one payment buys
            // one period (stories-only plans), nothing is charged again until the restaurant pays for the next one.
            $table->string('payment_mode', 12)->default('recurring')->after('auto_renew');
            $table->string('gateway_order_id')->nullable()->after('gateway_subscription_id');
            // An upgrade: the subscription this one takes over from once its payment succeeds.
            $table->unsignedBigInteger('replaces_subscription_id')->nullable()->after('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'gateway_order_id', 'replaces_subscription_id']);
        });
    }
};
