<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo')->nullable();
            $table->string('owner_name');
            $table->string('owner_email')->unique();
            $table->string('owner_phone')->unique();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('trade_license')->nullable();
            $table->string('status')->default('active')->index();
            $table->decimal('platform_commission_percent', 8, 2)->default(15);
            $table->decimal('branch_share_percent', 8, 2)->default(70);
            $table->decimal('admin_share_percent', 8, 2)->default(30);
            $table->string('settlement_cycle')->default('weekly');
            $table->json('bank_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branch_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->index();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('branch_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->unique()->constrained('branches')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('locked_balance', 12, 2)->default(0);
            $table->decimal('lifetime_earnings', 12, 2)->default(0);
            $table->decimal('lifetime_settled', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('branch_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_wallet_id')->constrained('branch_wallets')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('settlement_id')->nullable();
            $table->string('type')->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('settlement_number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_orders', 12, 2)->default(0);
            $table->decimal('platform_commission', 12, 2)->default(0);
            $table->decimal('branch_commission', 12, 2)->default(0);
            $table->decimal('admin_commission', 12, 2)->default(0);
            $table->decimal('adjustments', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('branch_settlement_id')->nullable()->constrained('branch_settlements')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('transaction_reference')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->string('pincode')->nullable();
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['city', 'area', 'pincode'], 'branch_zones_unique_territory');
        });

        Schema::create('branch_restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->unique()->constrained('restaurants')->cascadeOnDelete();
            $table->string('approval_status')->default('branch_pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('driver_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('approval_status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('branch_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('channel')->default('database');
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('category')->index();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal');
            $table->timestamps();
        });

        Schema::create('branch_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_ticket_id')->constrained('branch_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('platform_commission_percent', 8, 2);
            $table->decimal('branch_share_percent', 8, 2);
            $table->decimal('admin_share_percent', 8, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('branch_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('branch_transfer_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('transferable_type');
            $table->unsignedBigInteger('transferable_id');
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['transferable_type', 'transferable_id'], 'branch_transfer_entity_index');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('owner_id')->constrained('branches')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('current_restaurant_id')->constrained('branches')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'branch_commission')) {
                $table->decimal('branch_commission', 12, 2)->default(0)->after('platform_commission');
            }
            if (! Schema::hasColumn('orders', 'branch_commission_settled')) {
                $table->boolean('branch_commission_settled')->default(false)->after('branch_commission');
            }
            if (! Schema::hasColumn('orders', 'cod_reconciliation_status')) {
                $table->string('cod_reconciliation_status')->nullable()->after('delivery_payment_mode')->index();
            }
            if (! Schema::hasColumn('orders', 'cod_deposited_at')) {
                $table->timestamp('cod_deposited_at')->nullable()->after('cash_collected_at');
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['branch_commission', 'branch_commission_settled', 'cod_reconciliation_status', 'cod_deposited_at'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        foreach (['addresses', 'orders', 'users', 'restaurants'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            }
        }

        Schema::dropIfExists('branch_transfer_history');
        Schema::dropIfExists('branch_audit_logs');
        Schema::dropIfExists('branch_commission_rules');
        Schema::dropIfExists('branch_ticket_messages');
        Schema::dropIfExists('branch_tickets');
        Schema::dropIfExists('branch_notifications');
        Schema::dropIfExists('branch_drivers');
        Schema::dropIfExists('branch_restaurants');
        Schema::dropIfExists('branch_zones');
        Schema::dropIfExists('branch_payouts');
        Schema::dropIfExists('branch_settlements');
        Schema::dropIfExists('branch_wallet_transactions');
        Schema::dropIfExists('branch_wallets');
        Schema::dropIfExists('branch_users');
        Schema::dropIfExists('branches');
    }
};
