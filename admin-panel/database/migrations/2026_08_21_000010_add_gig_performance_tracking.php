<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_gig_bookings')) {
            Schema::table('driver_gig_bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('driver_gig_bookings', 'checked_in_at')) {
                    $table->timestamp('checked_in_at')->nullable()->after('booked_at');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'checked_out_at')) {
                    $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'online_minutes')) {
                    $table->unsignedInteger('online_minutes')->default(0)->after('checked_out_at');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'delivered_orders_count')) {
                    $table->unsignedInteger('delivered_orders_count')->default(0)->after('online_minutes');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'rejected_orders_count')) {
                    $table->unsignedInteger('rejected_orders_count')->default(0)->after('delivered_orders_count');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'cancelled_orders_count')) {
                    $table->unsignedInteger('cancelled_orders_count')->default(0)->after('rejected_orders_count');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'no_show')) {
                    $table->boolean('no_show')->default(false)->after('cancelled_orders_count');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'incentive_paid_at')) {
                    $table->timestamp('incentive_paid_at')->nullable()->after('no_show');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'reminder_sent_at')) {
                    $table->timestamp('reminder_sent_at')->nullable()->after('incentive_paid_at');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'start_warning_sent_at')) {
                    $table->timestamp('start_warning_sent_at')->nullable()->after('reminder_sent_at');
                }
                if (! Schema::hasColumn('driver_gig_bookings', 'metrics')) {
                    $table->json('metrics')->nullable()->after('start_warning_sent_at');
                }
            });
        }

        if (Schema::hasTable('gig_incentives')) {
            Schema::table('gig_incentives', function (Blueprint $table) {
                if (! Schema::hasColumn('gig_incentives', 'login_requirement_met')) {
                    $table->boolean('login_requirement_met')->default(false)->after('active_minutes');
                }
                if (! Schema::hasColumn('gig_incentives', 'order_requirement_met')) {
                    $table->boolean('order_requirement_met')->default(false)->after('login_requirement_met');
                }
                if (! Schema::hasColumn('gig_incentives', 'cancellation_requirement_met')) {
                    $table->boolean('cancellation_requirement_met')->default(true)->after('order_requirement_met');
                }
                if (! Schema::hasColumn('gig_incentives', 'no_show')) {
                    $table->boolean('no_show')->default(false)->after('cancellation_requirement_met');
                }
                if (! Schema::hasColumn('gig_incentives', 'delivered_orders_count')) {
                    $table->unsignedInteger('delivered_orders_count')->default(0)->after('no_show');
                }
                if (! Schema::hasColumn('gig_incentives', 'rejected_orders_count')) {
                    $table->unsignedInteger('rejected_orders_count')->default(0)->after('delivered_orders_count');
                }
                if (! Schema::hasColumn('gig_incentives', 'cancelled_orders_count')) {
                    $table->unsignedInteger('cancelled_orders_count')->default(0)->after('rejected_orders_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gig_incentives')) {
            Schema::table('gig_incentives', function (Blueprint $table) {
                foreach ([
                    'cancelled_orders_count',
                    'rejected_orders_count',
                    'delivered_orders_count',
                    'no_show',
                    'cancellation_requirement_met',
                    'order_requirement_met',
                    'login_requirement_met',
                ] as $column) {
                    if (Schema::hasColumn('gig_incentives', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('driver_gig_bookings')) {
            Schema::table('driver_gig_bookings', function (Blueprint $table) {
                foreach ([
                    'metrics',
                    'start_warning_sent_at',
                    'reminder_sent_at',
                    'incentive_paid_at',
                    'no_show',
                    'cancelled_orders_count',
                    'rejected_orders_count',
                    'delivered_orders_count',
                    'online_minutes',
                    'checked_out_at',
                    'checked_in_at',
                ] as $column) {
                    if (Schema::hasColumn('driver_gig_bookings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};