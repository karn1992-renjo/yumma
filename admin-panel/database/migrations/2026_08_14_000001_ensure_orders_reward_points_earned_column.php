<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'reward_points_earned')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('reward_points_earned')->default(0);
            });
        }
    }

    public function down(): void
    {
        // This migration repairs schema drift for a column owned by an earlier migration.
    }
};
