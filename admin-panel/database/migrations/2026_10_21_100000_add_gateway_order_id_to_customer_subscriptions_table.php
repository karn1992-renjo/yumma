<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            // Gold membership is pay-as-you-go: a one-time gateway order
            // (same Orders API already used for checkout/ad-wallet top-up),
            // verified synchronously right after the client's gateway SDK
            // confirms payment -- no recurring mandate, no webhook. This is
            // the gateway's order id for that one-time charge, distinct from
            // gateway_subscription_id (unused for Gold now, kept only for
            // restaurant subscriptions, which still use a recurring mandate).
            $table->string('gateway_order_id')->nullable()->after('gateway_subscription_id');
        });
    }

    public function down()
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropColumn('gateway_order_id');
        });
    }
};
