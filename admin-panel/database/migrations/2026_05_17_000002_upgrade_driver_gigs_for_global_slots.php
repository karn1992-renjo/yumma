<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->change();
            $table->string('title')->nullable()->after('id');
            $table->text('description')->nullable()->after('title');
            $table->decimal('base_pay', 10, 2)->default(0)->after('status');
            $table->decimal('order_incentive', 10, 2)->default(0)->after('base_pay');
            $table->decimal('login_incentive', 10, 2)->default(0)->after('order_incentive');
            $table->unsignedInteger('min_orders_required')->default(0)->after('login_incentive');
            $table->unsignedInteger('min_login_minutes')->default(0)->after('min_orders_required');
            $table->unsignedInteger('max_cancellations_allowed')->default(0)->after('min_login_minutes');
            $table->text('terms_conditions')->nullable()->after('max_cancellations_allowed');
            $table->timestamp('booked_at')->nullable()->after('terms_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'description',
                'base_pay',
                'order_incentive',
                'login_incentive',
                'min_orders_required',
                'min_login_minutes',
                'max_cancellations_allowed',
                'terms_conditions',
                'booked_at',
            ]);
        });
    }
};
