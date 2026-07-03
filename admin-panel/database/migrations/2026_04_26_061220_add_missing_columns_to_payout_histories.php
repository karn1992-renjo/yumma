<?php
// database/migrations/2026_04_26_061220_add_missing_columns_to_payout_histories.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payout_histories', function (Blueprint $table) {
            // Check and add columns only if they don't exist
            if (!Schema::hasColumn('payout_histories', 'payable_type')) {
                $table->string('payable_type')->after('id');
            }
            if (!Schema::hasColumn('payout_histories', 'payable_id')) {
                $table->integer('payable_id')->after('payable_type');
            }
            if (!Schema::hasColumn('payout_histories', 'amount')) {
                $table->decimal('amount', 12, 2)->after('payable_id');
            }
            if (!Schema::hasColumn('payout_histories', 'period_type')) {
                $table->string('period_type')->after('amount');
            }
            if (!Schema::hasColumn('payout_histories', 'period_start')) {
                $table->date('period_start')->after('period_type');
            }
            if (!Schema::hasColumn('payout_histories', 'period_end')) {
                $table->date('period_end')->after('period_start');
            }
            if (!Schema::hasColumn('payout_histories', 'status')) {
                $table->string('status')->default('pending')->after('period_end');
            }
            if (!Schema::hasColumn('payout_histories', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payout_histories', 'breakdown')) {
                $table->json('breakdown')->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('payout_histories', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('breakdown');
            }
        });

        // Add index separately with existence check
        try {
            $indexExists = false;
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='payout_histories'");
            
            foreach ($indexes as $index) {
                if ($index->name === 'payout_histories_payable_type_payable_id_period_start_period_end_index') {
                    $indexExists = true;
                    break;
                }
            }
            
            if (!$indexExists) {
                Schema::table('payout_histories', function (Blueprint $table) {
                    $table->index(['payable_type', 'payable_id', 'period_start', 'period_end'], 'payout_histories_payable_idx');
                });
            }
        } catch (\Exception $e) {
            // Index already exists or can't be created, continue
        }
    }

    public function down()
    {
        // No need to drop as these are additions
    }
};