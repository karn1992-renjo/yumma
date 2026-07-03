<?php
// database/migrations/2024_01_01_000002_add_refund_columns_to_orders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefundColumnsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refund_amount', 10, 2)->nullable()->after('total');
            $table->string('refund_reason')->nullable()->after('cancellation_reason');
            $table->enum('refund_status', ['pending', 'processing', 'completed', 'failed'])->nullable()->after('payment_status');
            $table->timestamp('refund_processed_at')->nullable();
            $table->string('refund_transaction_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_reason', 'refund_status', 'refund_processed_at', 'refund_transaction_id']);
        });
    }
}