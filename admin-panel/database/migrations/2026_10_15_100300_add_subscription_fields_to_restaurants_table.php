<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->enum('billing_mode', ['commission', 'subscription'])->default('commission')->after('commission_calculation_type');
            $table->foreignId('active_subscription_id')->nullable()->after('billing_mode')
                ->constrained('restaurant_subscriptions')->nullOnDelete();
            $table->timestamp('commission_consent_accepted_at')->nullable()->after('active_subscription_id');
            $table->decimal('commission_consent_rate_shown', 5, 2)->nullable()->after('commission_consent_accepted_at');
        });
    }

    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_subscription_id');
            $table->dropColumn(['billing_mode', 'commission_consent_accepted_at', 'commission_consent_rate_shown']);
        });
    }
};
