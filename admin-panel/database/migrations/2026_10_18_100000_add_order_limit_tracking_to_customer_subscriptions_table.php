<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // Mirrors restaurant_subscriptions.orders_used_in_period -- lets a
            // Gold plan cap how many orders get the free-delivery waiver per
            // billing period (plan.order_limit, NULL = unlimited). Reset to 0
            // on each successful renewal.
            $table->unsignedInteger('orders_used_in_period')->default(0)->after('current_period_end');
        });
    }

    public function down()
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('orders_used_in_period');
        });
    }
};
