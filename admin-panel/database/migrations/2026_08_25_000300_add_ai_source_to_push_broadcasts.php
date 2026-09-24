<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            if (! Schema::hasColumn('push_broadcasts', 'source')) {
                $table->string('source', 20)->default('admin')->after('sent_by');
            }
            if (! Schema::hasColumn('push_broadcasts', 'role_group')) {
                $table->string('role_group', 20)->nullable()->after('source');
            }
            if (! Schema::hasColumn('push_broadcasts', 'ai_decision_id')) {
                $table->foreignId('ai_decision_id')->nullable()->after('role_group')
                    ->constrained('ai_decisions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_broadcasts', function (Blueprint $table) {
            if (Schema::hasColumn('push_broadcasts', 'ai_decision_id')) {
                $table->dropForeign(['ai_decision_id']);
            }
            $columns = array_filter(
                ['source', 'role_group', 'ai_decision_id'],
                fn ($column) => Schema::hasColumn('push_broadcasts', $column)
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
