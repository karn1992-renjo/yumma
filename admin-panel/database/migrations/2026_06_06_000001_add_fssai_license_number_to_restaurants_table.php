<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'fssai_license_number')) {
                $table->string('fssai_license_number', 64)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'fssai_license_number')) {
                $table->dropColumn('fssai_license_number');
            }
        });
    }
};
