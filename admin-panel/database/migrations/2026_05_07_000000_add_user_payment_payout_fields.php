<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach ([
                    'account_holder_name' => 'string',
                    'bank_name' => 'string',
                    'account_number' => 'string',
                    'ifsc_code' => 'string',
                    'upi_id' => 'string',
                    'gateway_account_id' => 'string',
                ] as $column => $type) {
                    if (!Schema::hasColumn('users', $column)) {
                        $table->{$type}($column)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach ([
                    'gateway_account_id',
                    'upi_id',
                    'ifsc_code',
                    'account_number',
                    'bank_name',
                    'account_holder_name',
                ] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
