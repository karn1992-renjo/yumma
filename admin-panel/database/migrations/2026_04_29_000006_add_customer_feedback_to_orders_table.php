<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'restaurant_rating')) {
                $table->unsignedTinyInteger('restaurant_rating')->nullable()->after('refund_processed_at');
            }
            if (!Schema::hasColumn('orders', 'driver_rating')) {
                $table->unsignedTinyInteger('driver_rating')->nullable()->after('restaurant_rating');
            }
            if (!Schema::hasColumn('orders', 'restaurant_feedback')) {
                $table->text('restaurant_feedback')->nullable()->after('driver_rating');
            }
            if (!Schema::hasColumn('orders', 'driver_feedback')) {
                $table->text('driver_feedback')->nullable()->after('restaurant_feedback');
            }
            if (!Schema::hasColumn('orders', 'feedback_submitted_at')) {
                $table->timestamp('feedback_submitted_at')->nullable()->after('driver_feedback');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'feedback_submitted_at',
                'driver_feedback',
                'restaurant_feedback',
                'driver_rating',
                'restaurant_rating',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
