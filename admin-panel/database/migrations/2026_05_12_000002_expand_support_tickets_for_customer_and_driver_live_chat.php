<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'requester_role')) {
                $table->string('requester_role')->default('customer')->after('user_id');
                $table->index('requester_role');
            }
        });

        DB::table('support_tickets')
            ->whereNotNull('restaurant_id')
            ->update(['requester_role' => 'restaurant']);

        if (Schema::hasColumn('support_tickets', 'restaurant_id')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'requester_role')) {
                $table->dropIndex(['requester_role']);
                $table->dropColumn('requester_role');
            }
        });
    }
};
