<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add account holder name for all payment gateway types
            if (!Schema::hasColumn('users', 'account_holder_name')) {
                $table->string('account_holder_name')->nullable()->after('bank_name');
            }

            // Add UPI ID for Indian payment gateways
            if (!Schema::hasColumn('users', 'upi_id')) {
                $table->string('upi_id')->nullable()->after('ifsc_code');
            }

            // Add generic gateway account ID for marketplace payout features
            if (!Schema::hasColumn('users', 'gateway_account_id')) {
                $table->string('gateway_account_id')->nullable()->after('stripe_account_id');
            }

            // Add encrypted routing code field for bank-based gateways
            if (!Schema::hasColumn('users', 'routing_code')) {
                $table->string('routing_code')->nullable()->after('gateway_account_id');
            }

            // Add payout gateway provider to track which gateway is being used
            if (!Schema::hasColumn('users', 'payout_gateway')) {
                $table->string('payout_gateway')->nullable()->after('routing_code');
            }

            // Add payout country code to support multi-country payout profiles
            if (!Schema::hasColumn('users', 'payout_country')) {
                $table->string('payout_country')->nullable()->after('payout_gateway');
            }

            // Encrypt sensitive payout fields
            $sensitiveFields = [
                'bank_name',
                'account_number',
                'account_holder_name',
                'ifsc_code',
                'upi_id',
                'stripe_account_id',
                'gateway_account_id',
                'routing_code',
            ];

            // Note: Laravel's encryption is typically done at the model level,
            // not at the migration level. This comment serves as documentation
            // that these fields should be added to the $encrypted array in the User model.
        });

        Cache::forget('app_settings');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToRemove = [
                'account_holder_name',
                'upi_id',
                'gateway_account_id',
                'routing_code',
                'payout_gateway',
                'payout_country',
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Cache::forget('app_settings');
    }
};
