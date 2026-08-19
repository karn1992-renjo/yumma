<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotions')) {
            $this->addPromotionColumns();
        }

        if (Schema::hasTable('promotion_usage')) {
            $this->addPromotionUsageColumns();
        }

        if (Schema::hasTable('orders')) {
            $this->addOrderLiabilityColumns();
        }

        if (Schema::hasTable('promotion_settlement_ledgers')) {
            $this->addSettlementLedgerColumns();
        }
    }

    public function down(): void
    {
        // These columns are owned by the original promotion accounting migration.
    }

    private function addPromotionColumns(): void
    {
        if (! Schema::hasColumn('promotions', 'funding_type')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->string('funding_type', 40)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('promotions', 'platform_share_percent')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->decimal('platform_share_percent', 5, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('promotions', 'restaurant_share_percent')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->decimal('restaurant_share_percent', 5, 2)->nullable();
            });
        }

        if (! Schema::hasColumn('promotions', 'partner_name')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->string('partner_name')->nullable();
            });
        }

        foreach (['total_budget', 'daily_budget', 'per_restaurant_budget'] as $column) {
            if (! Schema::hasColumn('promotions', $column)) {
                Schema::table('promotions', function (Blueprint $table) use ($column) {
                    $table->decimal($column, 12, 2)->nullable();
                });
            }
        }

        if (! Schema::hasColumn('promotions', 'budget_used')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->decimal('budget_used', 12, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('promotions', 'fraud_rules')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->json('fraud_rules')->nullable();
            });
        }
    }

    private function addPromotionUsageColumns(): void
    {
        foreach ([
            'gross_liability_amount',
            'platform_liability_amount',
            'restaurant_liability_amount',
            'partner_liability_amount',
        ] as $column) {
            if (! Schema::hasColumn('promotion_usage', $column)) {
                Schema::table('promotion_usage', function (Blueprint $table) use ($column) {
                    $table->decimal($column, 12, 2)->default(0);
                });
            }
        }

        if (! Schema::hasColumn('promotion_usage', 'funding_breakdown')) {
            Schema::table('promotion_usage', function (Blueprint $table) {
                $table->json('funding_breakdown')->nullable();
            });
        }
    }

    private function addOrderLiabilityColumns(): void
    {
        foreach ([
            'promotion_platform_liability',
            'promotion_restaurant_liability',
            'promotion_partner_liability',
        ] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column) {
                    $table->decimal($column, 12, 2)->default(0);
                });
            }
        }

        if (! Schema::hasColumn('orders', 'reward_points_earned')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('reward_points_earned')->default(0);
            });
        }
    }

    private function addSettlementLedgerColumns(): void
    {
        if (! Schema::hasColumn('promotion_settlement_ledgers', 'coupon_code')) {
            Schema::table('promotion_settlement_ledgers', function (Blueprint $table) {
                $table->string('coupon_code')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('promotion_settlement_ledgers', 'funding_type')) {
            Schema::table('promotion_settlement_ledgers', function (Blueprint $table) {
                $table->string('funding_type', 40)->default('platform')->index();
            });
        }

        if (! Schema::hasColumn('promotion_settlement_ledgers', 'partner_name')) {
            Schema::table('promotion_settlement_ledgers', function (Blueprint $table) {
                $table->string('partner_name')->nullable();
            });
        }

        foreach ([
            'discount_amount',
            'cashback_amount',
            'gift_voucher_amount',
            'gross_liability_amount',
            'platform_liability_amount',
            'restaurant_liability_amount',
            'partner_liability_amount',
        ] as $column) {
            if (! Schema::hasColumn('promotion_settlement_ledgers', $column)) {
                Schema::table('promotion_settlement_ledgers', function (Blueprint $table) use ($column) {
                    $table->decimal($column, 12, 2)->default(0);
                });
            }
        }

        if (! Schema::hasColumn('promotion_settlement_ledgers', 'settlement_status')) {
            Schema::table('promotion_settlement_ledgers', function (Blueprint $table) {
                $table->string('settlement_status', 40)->default('pending')->index();
            });
        }

        foreach (['line_payload', 'funding_breakdown'] as $column) {
            if (! Schema::hasColumn('promotion_settlement_ledgers', $column)) {
                Schema::table('promotion_settlement_ledgers', function (Blueprint $table) use ($column) {
                    $table->json($column)->nullable();
                });
            }
        }
    }
};
