<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_settlement_ledgers')) {
            return;
        }

        Schema::create('promotion_settlement_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id')->nullable()->index();
            $table->unsignedBigInteger('promotion_usage_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('restaurant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
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

            $table->unique(
                ['order_id', 'promotion_id', 'coupon_code'],
                'promo_ledger_order_promo_coupon_unique'
            );
            $table->index(['restaurant_id', 'created_at']);
            $table->index(['promotion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // This migration repairs a table owned by the original accounting migration.
    }
};
