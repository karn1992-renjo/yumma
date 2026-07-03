<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_settings', function (Blueprint $table) {
            $table->decimal('rate', 12, 2)->change();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('commission_rate', 12, 2)->nullable()->default(null)->change();
            $table->string('commission_calculation_type')
                ->default('percentage')
                ->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('commission_calculation_type');
        });

        DB::table('restaurants')->whereNull('commission_rate')->update(['commission_rate' => 15]);
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(15)->nullable(false)->change();
        });

        Schema::table('commission_settings', function (Blueprint $table) {
            $table->decimal('rate', 5, 2)->change();
        });
    }
};
