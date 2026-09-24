<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'earning_mode')) {
                // 'commission' = per-delivery payout (default), 'salary' = fixed monthly.
                $table->string('earning_mode')->default('commission')->after('id');
            }
            if (! Schema::hasColumn('users', 'monthly_salary')) {
                $table->decimal('monthly_salary', 12, 2)->nullable()->after('earning_mode');
            }
            if (! Schema::hasColumn('users', 'salary_effective_from')) {
                $table->date('salary_effective_from')->nullable()->after('monthly_salary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['earning_mode', 'monthly_salary', 'salary_effective_from'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
