<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_indexes') && ! Schema::hasColumn('search_indexes', 'is_sponsored')) {
            Schema::table('search_indexes', function (Blueprint $table) {
                $table->boolean('is_sponsored')->default(false)->after('search_score');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('search_indexes') && Schema::hasColumn('search_indexes', 'is_sponsored')) {
            Schema::table('search_indexes', function (Blueprint $table) {
                $table->dropColumn('is_sponsored');
            });
        }
    }
};
