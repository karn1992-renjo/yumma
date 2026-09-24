<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_usage_logs', 'context')) {
                // Free-form tag for non-text usage, e.g. "campaign:free_delivery".
                $table->string('context', 80)->nullable()->after('model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            if (Schema::hasColumn('ai_usage_logs', 'context')) {
                $table->dropColumn('context');
            }
        });
    }
};
