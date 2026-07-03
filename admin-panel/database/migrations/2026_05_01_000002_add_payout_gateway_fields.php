<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (Schema::hasColumn('payouts', 'restaurant_id')) {
                $table->unsignedBigInteger('restaurant_id')->nullable()->change();
            }
            if (!Schema::hasColumn('payouts', 'gateway')) {
                $table->string('gateway')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('payouts', 'gateway_response')) {
                $table->json('gateway_response')->nullable()->after('gateway');
            }
            if (!Schema::hasColumn('payouts', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('gateway_response');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'bank_name' => 'string',
                'account_number' => 'string',
                'ifsc_code' => 'string',
                'razorpay_contact_id' => 'string',
                'razorpay_fund_account_id' => 'string',
                'stripe_account_id' => 'string',
                'cashfree_beneficiary_id' => 'string',
            ] as $column => $type) {
                if (!Schema::hasColumn('users', $column)) {
                    $table->{$type}($column)->nullable();
                }
            }
        });

        AppSetting::updateOrCreate(
            ['key' => 'auto_payout_enabled'],
            ['value' => '0', 'type' => 'boolean', 'description' => 'Automatically process pending payouts using the selected payment gateway.']
        );
        AppSetting::updateOrCreate(
            ['key' => 'payout_frequency'],
            ['value' => 'weekly', 'type' => 'select', 'description' => 'Auto payout frequency: daily, weekly, monthly.']
        );
        AppSetting::updateOrCreate(
            ['key' => 'payout_gateway_provider'],
            ['value' => 'razorpay', 'type' => 'select', 'description' => 'Gateway used for payouts: razorpay, stripe, cashfree.']
        );
        AppSetting::updateOrCreate(
            ['key' => 'razorpay_x_account_number'],
            ['value' => '', 'type' => 'text', 'description' => 'RazorpayX account number used for payouts.']
        );

        Cache::forget('app_settings');
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            foreach (['gateway', 'gateway_response', 'failure_reason'] as $column) {
                if (Schema::hasColumn('payouts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Cache::forget('app_settings');
    }
};
