<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurants', 'delivery_radius')) {
                $table->decimal('delivery_radius', 8, 2)->default(10)->after('delivery_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'delivery_radius')) {
                $table->dropColumn('delivery_radius');
            }
        });
    }
};
