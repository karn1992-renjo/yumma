<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'voice_remaining_seconds')) {
                $table->unsignedInteger('voice_remaining_seconds')->nullable()->after('reward_points_balance');
            }
            if (! Schema::hasColumn('users', 'voice_last_recharged_order_id')) {
                $table->unsignedBigInteger('voice_last_recharged_order_id')->nullable()->after('voice_remaining_seconds');
            }
            if (! Schema::hasColumn('users', 'voice_last_recharged_at')) {
                $table->timestamp('voice_last_recharged_at')->nullable()->after('voice_last_recharged_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'voice_last_recharged_at')) {
                $table->dropColumn('voice_last_recharged_at');
            }
            if (Schema::hasColumn('users', 'voice_last_recharged_order_id')) {
                $table->dropColumn('voice_last_recharged_order_id');
            }
            if (Schema::hasColumn('users', 'voice_remaining_seconds')) {
                $table->dropColumn('voice_remaining_seconds');
            }
        });
    }
};
