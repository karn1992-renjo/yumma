<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_applications', 'pan_number')) {
                $table->string('pan_number')->nullable()->after('gstin_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            if (Schema::hasColumn('partner_applications', 'pan_number')) {
                $table->dropColumn('pan_number');
            }
        });
    }
};
