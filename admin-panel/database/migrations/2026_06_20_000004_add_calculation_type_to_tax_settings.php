<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tax_settings', 'calculation_type')) {
                $table->string('calculation_type', 20)->default('percentage')->after('type');
            }
        });

        DB::table('tax_settings')
            ->where('type', 'packaging_charge')
            ->update(['calculation_type' => 'fixed']);
    }

    public function down(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tax_settings', 'calculation_type')) {
                $table->dropColumn('calculation_type');
            }
        });
    }
};
