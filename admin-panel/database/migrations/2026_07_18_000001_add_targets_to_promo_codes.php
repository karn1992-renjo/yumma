<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('promo_codes', 'target_type')) {
                $table->string('target_type')->default('restaurant')->after('audience_type');
            }

            if (! Schema::hasColumn('promo_codes', 'target_ids')) {
                $table->json('target_ids')->nullable()->after('target_type');
            }

            if (! Schema::hasColumn('promo_codes', 'promotion_type')) {
                $table->string('promotion_type')->default('percentage_discount')->after('coupon_type');
            }

            if (! Schema::hasColumn('promo_codes', 'reward_type')) {
                $table->string('reward_type')->nullable()->after('promotion_type');
            }

            if (! Schema::hasColumn('promo_codes', 'reward_config')) {
                $table->json('reward_config')->nullable()->after('reward_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            if (Schema::hasColumn('promo_codes', 'target_ids')) {
                $table->dropColumn('target_ids');
            }

            if (Schema::hasColumn('promo_codes', 'target_type')) {
                $table->dropColumn('target_type');
            }

            if (Schema::hasColumn('promo_codes', 'reward_config')) {
                $table->dropColumn('reward_config');
            }

            if (Schema::hasColumn('promo_codes', 'reward_type')) {
                $table->dropColumn('reward_type');
            }

            if (Schema::hasColumn('promo_codes', 'promotion_type')) {
                $table->dropColumn('promotion_type');
            }
        });
    }
};
