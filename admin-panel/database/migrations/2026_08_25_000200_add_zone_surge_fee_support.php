<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_areas', 'surge_fee_active')) {
                $table->boolean('surge_fee_active')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('delivery_areas', 'surge_fee_amount')) {
                $table->decimal('surge_fee_amount', 8, 2)->nullable()->after('surge_fee_active');
            }
            if (! Schema::hasColumn('delivery_areas', 'surge_fee_reason')) {
                $table->string('surge_fee_reason', 191)->nullable()->after('surge_fee_amount');
            }
            if (! Schema::hasColumn('delivery_areas', 'surge_fee_activated_at')) {
                $table->timestamp('surge_fee_activated_at')->nullable()->after('surge_fee_reason');
            }
            if (! Schema::hasColumn('delivery_areas', 'surge_fee_ai_decision_id')) {
                $table->foreignId('surge_fee_ai_decision_id')->nullable()->after('surge_fee_activated_at')
                    ->constrained('ai_decisions')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'surge_fee')) {
                $table->decimal('surge_fee', 10, 2)->default(0)->after('delivery_fee');
            }
        });

        Schema::table('gig_incentives', function (Blueprint $table) {
            if (! Schema::hasColumn('gig_incentives', 'zone_surge_bonus')) {
                $table->decimal('zone_surge_bonus', 10, 2)->default(0)->after('surge_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_areas', 'surge_fee_ai_decision_id')) {
                $table->dropForeign(['surge_fee_ai_decision_id']);
            }
            $columns = array_filter(
                ['surge_fee_active', 'surge_fee_amount', 'surge_fee_reason', 'surge_fee_activated_at', 'surge_fee_ai_decision_id'],
                fn ($column) => Schema::hasColumn('delivery_areas', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'surge_fee')) {
                $table->dropColumn('surge_fee');
            }
        });

        Schema::table('gig_incentives', function (Blueprint $table) {
            if (Schema::hasColumn('gig_incentives', 'zone_surge_bonus')) {
                $table->dropColumn('zone_surge_bonus');
            }
        });
    }
};
