<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('support_conversations', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('restaurant_id')->constrained('branches')->nullOnDelete();
            }

            // SLA-reminder idempotency -- see Support Desk plan: a scheduled
            // command flags stale/unassigned tickets, and uses this to avoid
            // re-notifying about the same ticket every time it runs.
            if (! Schema::hasColumn('support_conversations', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable()->after('resolve_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('support_conversations', 'last_reminded_at')) {
                $table->dropColumn('last_reminded_at');
            }

            if (Schema::hasColumn('support_conversations', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
