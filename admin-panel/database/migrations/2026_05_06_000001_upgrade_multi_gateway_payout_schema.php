<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payouts')) {
            try {
                DB::statement("ALTER TABLE payouts MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
            } catch (Throwable $e) {
                // Some drivers do not support MODIFY in tests; new columns still keep the app usable.
            }

            Schema::table('payouts', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'uuid', fn () => $table->uuid('uuid')->nullable()->after('id')->index());
                $this->addColumnIfMissing($table, 'batch_id', fn () => $table->string('batch_id')->nullable()->after('uuid')->index());
                $this->addColumnIfMissing($table, 'vendor_type', fn () => $table->string('vendor_type', 32)->nullable()->after('driver_id')->index());
                $this->addColumnIfMissing($table, 'vendor_id', fn () => $table->unsignedBigInteger('vendor_id')->nullable()->after('vendor_type')->index());
                $this->addColumnIfMissing($table, 'gross_amount', fn () => $table->decimal('gross_amount', 12, 2)->default(0)->after('amount'));
                $this->addColumnIfMissing($table, 'platform_commission', fn () => $table->decimal('platform_commission', 12, 2)->default(0)->after('gross_amount'));
                $this->addColumnIfMissing($table, 'delivery_fee', fn () => $table->decimal('delivery_fee', 12, 2)->default(0)->after('platform_commission'));
                $this->addColumnIfMissing($table, 'net_amount', fn () => $table->decimal('net_amount', 12, 2)->default(0)->after('deduction_reason'));
                $this->addColumnIfMissing($table, 'currency', fn () => $table->string('currency', 8)->default('INR')->after('net_amount'));
                $this->addColumnIfMissing($table, 'gateway_reference_id', fn () => $table->string('gateway_reference_id')->nullable()->after('transaction_id')->index());
                $this->addColumnIfMissing($table, 'gateway_status', fn () => $table->string('gateway_status', 64)->nullable()->after('gateway'));
                $this->addColumnIfMissing($table, 'idempotency_key', fn () => $table->string('idempotency_key')->nullable()->after('gateway_status')->unique());
                $this->addColumnIfMissing($table, 'vendor_bank_account_id', fn () => $table->unsignedBigInteger('vendor_bank_account_id')->nullable()->after('idempotency_key')->index());
                $this->addColumnIfMissing($table, 'retry_count', fn () => $table->unsignedSmallInteger('retry_count')->default(0)->after('failure_reason'));
                $this->addColumnIfMissing($table, 'next_retry_at', fn () => $table->timestamp('next_retry_at')->nullable()->after('retry_count')->index());
                $this->addColumnIfMissing($table, 'created_by', fn () => $table->foreignId('created_by')->nullable()->after('next_retry_at')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'processed_by', fn () => $table->foreignId('processed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete());
                $this->addColumnIfMissing($table, 'deleted_at', fn () => $table->softDeletes());
            });
        }

        if (!Schema::hasTable('vendor_bank_accounts')) {
            Schema::create('vendor_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('vendor_type', 32)->index();
                $table->unsignedBigInteger('vendor_id')->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('account_holder_name');
                $table->text('account_number_encrypted')->nullable();
                $table->string('account_number_last4', 8)->nullable();
                $table->text('ifsc_code_encrypted')->nullable();
                $table->text('upi_id_encrypted')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('gateway_contact_id')->nullable();
                $table->string('gateway_fund_account_id')->nullable();
                $table->string('stripe_account_id')->nullable();
                $table->string('cashfree_beneficiary_id')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['vendor_type', 'vendor_id', 'is_default']);
            });
        }

        if (!Schema::hasTable('payout_settings')) {
            Schema::create('payout_settings', function (Blueprint $table) {
                $table->id();
                $table->string('gateway', 32)->index();
                $table->boolean('is_active')->default(false);
                $table->boolean('auto_generate_enabled')->default(true);
                $table->boolean('auto_process_enabled')->default(false);
                $table->string('schedule_frequency', 32)->default('weekly');
                $table->string('schedule_day', 32)->nullable();
                $table->decimal('minimum_payout_amount', 12, 2)->default(100);
                $table->longText('credentials')->nullable();
                $table->longText('webhook_config')->nullable();
                $table->json('options')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('failed_payouts')) {
            Schema::create('failed_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
                $table->string('gateway', 32)->nullable()->index();
                $table->string('error_code')->nullable();
                $table->text('error_message')->nullable();
                $table->json('payload')->nullable();
                $table->unsignedSmallInteger('retry_count')->default(0);
                $table->timestamp('next_retry_at')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('payout_audit_logs')) {
            Schema::create('payout_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 64)->index();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('payout_settings')) {
            foreach (['razorpay', 'stripe', 'cashfree'] as $gateway) {
                DB::table('payout_settings')->updateOrInsert(
                    ['gateway' => $gateway],
                    [
                        'is_active' => $gateway === 'razorpay',
                        'auto_generate_enabled' => true,
                        'auto_process_enabled' => false,
                        'schedule_frequency' => 'weekly',
                        'schedule_day' => 'monday',
                        'minimum_payout_amount' => 100,
                        'credentials' => null,
                        'webhook_config' => null,
                        'options' => json_encode([]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_audit_logs');
        Schema::dropIfExists('failed_payouts');
        Schema::dropIfExists('payout_settings');
        Schema::dropIfExists('vendor_bank_accounts');
    }

    private function addColumnIfMissing(Blueprint $table, string $column, Closure $callback): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $callback();
        }
    }
};
