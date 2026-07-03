<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'deduction_amount')) {
                $table->decimal('deduction_amount', 12, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('payouts', 'deduction_reason')) {
                $table->string('deduction_reason')->nullable()->after('deduction_amount');
            }
            if (!Schema::hasColumn('payouts', 'deduction_revoked_at')) {
                $table->timestamp('deduction_revoked_at')->nullable()->after('deduction_reason');
            }
            if (!Schema::hasColumn('payouts', 'deduction_revoke_reason')) {
                $table->string('deduction_revoke_reason')->nullable()->after('deduction_revoked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'deduction_amount',
                'deduction_reason',
                'deduction_revoked_at',
                'deduction_revoke_reason',
            ], fn ($column) => Schema::hasColumn('payouts', $column)));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
