<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'reorder_nudged_at')) {
                $table->timestamp('reorder_nudged_at')->nullable()->after('notify_offers_promotions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reorder_nudged_at')) {
                $table->dropColumn('reorder_nudged_at');
            }
        });
    }
};
