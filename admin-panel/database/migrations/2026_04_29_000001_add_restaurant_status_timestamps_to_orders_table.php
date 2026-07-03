<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('scheduled_time');
            }

            if (!Schema::hasColumn('orders', 'preparing_at')) {
                $table->timestamp('preparing_at')->nullable()->after('confirmed_at');
            }

            if (!Schema::hasColumn('orders', 'ready_at')) {
                $table->timestamp('ready_at')->nullable()->after('preparing_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'ready_at')) {
                $table->dropColumn('ready_at');
            }

            if (Schema::hasColumn('orders', 'preparing_at')) {
                $table->dropColumn('preparing_at');
            }

            if (Schema::hasColumn('orders', 'confirmed_at')) {
                $table->dropColumn('confirmed_at');
            }
        });
    }
};
