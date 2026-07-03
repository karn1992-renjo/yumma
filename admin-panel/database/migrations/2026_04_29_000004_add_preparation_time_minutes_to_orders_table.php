<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'preparation_time_minutes')) {
                $table->unsignedSmallInteger('preparation_time_minutes')
                    ->nullable()
                    ->after('confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'preparation_time_minutes')) {
                $table->dropColumn('preparation_time_minutes');
            }
        });
    }
};

