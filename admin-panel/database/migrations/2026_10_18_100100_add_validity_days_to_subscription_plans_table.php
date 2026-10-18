<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Optional custom duration in days, overriding billing_period's
            // fixed monthly/quarterly/yearly math when set -- lets an admin
            // offer e.g. a 7-day or 45-day pass instead of being limited to
            // those three cadences. NULL = use billing_period as before.
            $table->unsignedInteger('validity_days')->nullable()->after('billing_period');
        });
    }

    public function down()
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('validity_days');
        });
    }
};
