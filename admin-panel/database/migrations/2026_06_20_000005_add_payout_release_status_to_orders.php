<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payout_status')) {
                $table->string('payout_status', 30)->default('pending')->after('payout_processed_at');
            }
            if (! Schema::hasColumn('orders', 'payout_released_at')) {
                $table->timestamp('payout_released_at')->nullable()->after('payout_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payout_released_at')) {
                $table->dropColumn('payout_released_at');
            }
            if (Schema::hasColumn('orders', 'payout_status')) {
                $table->dropColumn('payout_status');
            }
        });
    }
};
