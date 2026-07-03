<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_cancellation_limits', function (Blueprint $table) {
            if (! Schema::hasColumn('order_cancellation_limits', 'cancellation_window_minutes')) {
                $table->unsignedInteger('cancellation_window_minutes')->default(15)->after('penalty_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_cancellation_limits', function (Blueprint $table) {
            if (Schema::hasColumn('order_cancellation_limits', 'cancellation_window_minutes')) {
                $table->dropColumn('cancellation_window_minutes');
            }
        });
    }
};
