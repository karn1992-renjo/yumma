<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mollie_organization_id')) {
                $table->string('mollie_organization_id')->nullable()->after('gateway_account_id');
            }
            if (!Schema::hasColumn('users', 'mollie_access_token')) {
                $table->text('mollie_access_token')->nullable()->after('mollie_organization_id');
            }
            if (!Schema::hasColumn('users', 'mollie_refresh_token')) {
                $table->text('mollie_refresh_token')->nullable()->after('mollie_access_token');
            }
            if (!Schema::hasColumn('users', 'mollie_token_expires_at')) {
                $table->timestamp('mollie_token_expires_at')->nullable()->after('mollie_refresh_token');
            }
            if (!Schema::hasColumn('users', 'mercadopago_collector_id')) {
                $table->string('mercadopago_collector_id')->nullable()->after('mollie_token_expires_at');
            }
            if (!Schema::hasColumn('users', 'payout_provider_meta')) {
                $table->json('payout_provider_meta')->nullable()->after('mercadopago_collector_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'payout_provider_meta',
                'mercadopago_collector_id',
                'mollie_token_expires_at',
                'mollie_refresh_token',
                'mollie_access_token',
                'mollie_organization_id',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
