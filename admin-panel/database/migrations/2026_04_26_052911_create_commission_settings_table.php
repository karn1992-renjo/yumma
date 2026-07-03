// database/migrations/2026_01_01_000003_create_payout_histories_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payout_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // restaurant_id or driver_id
            $table->decimal('amount', 12, 2);
            $table->string('period_type'); // daily, weekly, monthly
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('transaction_id')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['payable_type', 'payable_id', 'period_start', 'period_end'], 'payout_histories_payable_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payout_histories');
    }
};