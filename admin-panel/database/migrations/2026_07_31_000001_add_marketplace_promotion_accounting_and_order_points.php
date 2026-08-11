<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'funding_type')) {
                $table->string('funding_type', 40)->nullable()->after('is_exclusive')->index();
            }
            if (! Schema::hasColumn('promotions', 'platform_share_percent')) {
                $table->decimal('platform_share_percent', 5, 2)->nullable()->after('funding_type');
            }
            if (! Schema::hasColumn('promotions', 'restaurant_share_percent')) {
                $table->decimal('restaurant_share_percent', 5, 2)->nullable()->after('platform_share_percent');
            }
            if (! Schema::hasColumn('promotions', 'partner_name')) {
                $table->string('partner_name')->nullable()->after('restaurant_share_percent');
            }
            if (! Schema::hasColumn('promotions', 'total_budget')) {
                $table->decimal('total_budget', 12, 2)->nullable()->after('partner_name');
            }
            if (! Schema::hasColumn('promotions', 'daily_budget')) {
                $table->decimal('daily_budget', 12, 2)->nullable()->after('total_budget');
            }
            if (! Schema::hasColumn('promotions', 'per_restaurant_budget')) {
                $table->decimal('per_restaurant_budget', 12, 2)->nullable()->after('daily_budget');
            }
            if (! Schema::hasColumn('promotions', 'budget_used')) {
                $table->decimal('budget_used', 12, 2)->default(0)->after('per_restaurant_budget');
            }
            if (! Schema::hasColumn('promotions', 'fraud_rules')) {
                $table->json('fraud_rules')->nullable()->after('stacking');
            }
        });

        Schema::table('promotion_usage', function (Blueprint $table) {
            if (! Schema::hasColumn('promotion_usage', 'gross_liability_amount')) {
                $table->decimal('gross_liability_amount', 12, 2)->default(0)->after('cashback_amount');
            }
            if (! Schema::hasColumn('promotion_usage', 'platform_liability_amount')) {
                $table->decimal('platform_liability_amount', 12, 2)->default(0)->after('gross_liability_amount');
            }
            if (! Schema::hasColumn('promotion_usage', 'restaurant_liability_amount')) {
                $table->decimal('restaurant_liability_amount', 12, 2)->default(0)->after('platform_liability_amount');
            }
            if (! Schema::hasColumn('promotion_usage', 'partner_liability_amount')) {
                $table->decimal('partner_liability_amount', 12, 2)->default(0)->after('restaurant_liability_amount');
            }
            if (! Schema::hasColumn('promotion_usage', 'funding_breakdown')) {
                $table->json('funding_breakdown')->nullable()->after('partner_liability_amount');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'promotion_platform_liability')) {
                $table->decimal('promotion_platform_liability', 12, 2)->default(0)->after('restaurant_delivery_subsidy');
            }
            if (! Schema::hasColumn('orders', 'promotion_restaurant_liability')) {
                $table->decimal('promotion_restaurant_liability', 12, 2)->default(0)->after('promotion_platform_liability');
            }
            if (! Schema::hasColumn('orders', 'promotion_partner_liability')) {
                $table->decimal('promotion_partner_liability', 12, 2)->default(0)->after('promotion_restaurant_liability');
            }
            if (! Schema::hasColumn('orders', 'reward_points_earned')) {
                $table->unsignedInteger('reward_points_earned')->default(0)->after('promotion_partner_liability');
            }
        });

        if (! Schema::hasTable('promotion_settlement_ledgers')) {
            Schema::create('promotion_settlement_ledgers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('promotion_usage_id')->nullable()->constrained('promotion_usage')->nullOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('coupon_code')->nullable()->index();
                $table->string('funding_type', 40)->default('platform')->index();
                $table->string('partner_name')->nullable();
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('cashback_amount', 12, 2)->default(0);
                $table->decimal('gift_voucher_amount', 12, 2)->default(0);
                $table->decimal('gross_liability_amount', 12, 2)->default(0);
                $table->decimal('platform_liability_amount', 12, 2)->default(0);
                $table->decimal('restaurant_liability_amount', 12, 2)->default(0);
                $table->decimal('partner_liability_amount', 12, 2)->default(0);
                $table->string('settlement_status', 40)->default('pending')->index();
                $table->json('line_payload')->nullable();
                $table->json('funding_breakdown')->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'promotion_id', 'coupon_code'], 'promo_ledger_order_promo_coupon_unique');
                $table->index(['restaurant_id', 'created_at']);
                $table->index(['promotion_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_settlement_ledgers');

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'reward_points_earned',
                'promotion_partner_liability',
                'promotion_restaurant_liability',
                'promotion_platform_liability',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('promotion_usage', function (Blueprint $table) {
            foreach ([
                'funding_breakdown',
                'partner_liability_amount',
                'restaurant_liability_amount',
                'platform_liability_amount',
                'gross_liability_amount',
            ] as $column) {
                if (Schema::hasColumn('promotion_usage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            foreach ([
                'fraud_rules',
                'budget_used',
                'per_restaurant_budget',
                'daily_budget',
                'total_budget',
                'partner_name',
                'restaurant_share_percent',
                'platform_share_percent',
                'funding_type',
            ] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
