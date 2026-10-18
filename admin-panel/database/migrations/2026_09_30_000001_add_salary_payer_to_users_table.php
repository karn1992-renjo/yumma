<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'salary_payer')) {
                // Who is financially responsible for a salaried driver's monthly_salary:
                // 'admin' or 'branch'. Bookkeeping only -- no wallet debit happens from
                // this in-app; actual salary payment is external (HRMS/manual), same as
                // monthly_salary itself already is.
                $table->string('salary_payer')->nullable()->after('salary_effective_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'salary_payer')) {
                $table->dropColumn('salary_payer');
            }
        });
    }
};
