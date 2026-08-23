<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_gigs')) {
            Schema::table('driver_gigs', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_gigs', 'forecasted_orders')) {
                    $table->unsignedInteger('forecasted_orders')->default(0)->after('orders_count');
                }
                if (! Schema::hasColumn('driver_gigs', 'recommended_capacity')) {
                    $table->unsignedInteger('recommended_capacity')->nullable()->after('forecasted_orders');
                }
                if (! Schema::hasColumn('driver_gigs', 'demand_score')) {
                    $table->decimal('demand_score', 8, 2)->default(0)->after('recommended_capacity');
                }
                if (! Schema::hasColumn('driver_gigs', 'surge_multiplier')) {
                    $table->decimal('surge_multiplier', 5, 2)->default(1)->after('demand_score');
                }
                if (! Schema::hasColumn('driver_gigs', 'auto_pricing_enabled')) {
                    $table->boolean('auto_pricing_enabled')->default(true)->after('surge_multiplier');
                }
                if (! Schema::hasColumn('driver_gigs', 'forecast_meta')) {
                    $table->json('forecast_meta')->nullable()->after('auto_pricing_enabled');
                }
            });
        }

        if (Schema::hasTable('gig_incentives')) {
            Schema::table('gig_incentives', function (Blueprint $table) {
                if (! Schema::hasColumn('gig_incentives', 'surge_multiplier')) {
                    $table->decimal('surge_multiplier', 5, 2)->default(1)->after('active_time_incentive');
                }
                if (! Schema::hasColumn('gig_incentives', 'surge_amount')) {
                    $table->decimal('surge_amount', 10, 2)->default(0)->after('surge_multiplier');
                }
                if (! Schema::hasColumn('gig_incentives', 'approval_status')) {
                    $table->string('approval_status', 24)->default('auto_approved')->after('penalty_reason')->index();
                }
                if (! Schema::hasColumn('gig_incentives', 'fraud_status')) {
                    $table->string('fraud_status', 24)->default('clear')->after('approval_status')->index();
                }
            });
        }

        if (! Schema::hasTable('gig_demand_forecasts')) {
            Schema::create('gig_demand_forecasts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();
                $table->date('date');
                $table->unsignedTinyInteger('hour');
                $table->unsignedInteger('historical_orders')->default(0);
                $table->unsignedInteger('forecasted_orders')->default(0);
                $table->unsignedInteger('recommended_capacity')->default(1);
                $table->decimal('demand_score', 8, 2)->default(0);
                $table->decimal('surge_multiplier', 5, 2)->default(1);
                $table->json('signals')->nullable();
                $table->timestamps();
                $table->unique(['area_id', 'date', 'hour'], 'gig_demand_forecasts_area_date_hour_unique');
            });
        }

        if (! Schema::hasTable('gig_fraud_signals')) {
            Schema::create('gig_fraud_signals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_gig_id')->nullable()->constrained('driver_gigs')->nullOnDelete();
                $table->foreignId('driver_gig_booking_id')->nullable()->constrained('driver_gig_bookings')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('signal_type', 80);
                $table->string('severity', 24)->default('medium')->index();
                $table->unsignedSmallInteger('score')->default(0);
                $table->string('status', 24)->default('open')->index();
                $table->json('evidence')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['driver_id', 'status']);
            });
        }

        if (! Schema::hasTable('gig_payout_approvals')) {
            Schema::create('gig_payout_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gig_incentive_id')->nullable()->constrained('gig_incentives')->nullOnDelete();
                $table->foreignId('driver_gig_id')->nullable()->constrained('driver_gigs')->nullOnDelete();
                $table->foreignId('driver_gig_booking_id')->nullable()->constrained('driver_gig_bookings')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('status', 24)->default('pending')->index();
                $table->json('risk_summary')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('admin_note')->nullable();
                $table->timestamps();
                $table->unique(['driver_gig_booking_id'], 'gig_payout_approvals_booking_unique');
            });
        }

        if (! Schema::hasTable('gig_disputes')) {
            Schema::create('gig_disputes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_gig_id')->nullable()->constrained('driver_gigs')->nullOnDelete();
                $table->foreignId('driver_gig_booking_id')->nullable()->constrained('driver_gig_bookings')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('gig_incentive_id')->nullable()->constrained('gig_incentives')->nullOnDelete();
                $table->string('reason', 120);
                $table->text('message')->nullable();
                $table->string('status', 24)->default('open')->index();
                $table->text('resolution_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['driver_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gig_disputes');
        Schema::dropIfExists('gig_payout_approvals');
        Schema::dropIfExists('gig_fraud_signals');
        Schema::dropIfExists('gig_demand_forecasts');

        if (Schema::hasTable('gig_incentives')) {
            Schema::table('gig_incentives', function (Blueprint $table) {
                foreach (['fraud_status', 'approval_status', 'surge_amount', 'surge_multiplier'] as $column) {
                    if (Schema::hasColumn('gig_incentives', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('driver_gigs')) {
            Schema::table('driver_gigs', function (Blueprint $table) {
                foreach (['forecast_meta', 'auto_pricing_enabled', 'surge_multiplier', 'demand_score', 'recommended_capacity', 'forecasted_orders'] as $column) {
                    if (Schema::hasColumn('driver_gigs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};