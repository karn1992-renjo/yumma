<?php
// database/migrations/2024_01_01_000001_create_refund_policies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRefundPoliciesTable extends Migration
{
    public function up()
    {
        Schema::create('refund_policies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Refund Policy');
            $table->text('content');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('refund_window_hours')->default(2);
            $table->decimal('restaurant_commission_rate', 5, 2)->default(15.00);
            $table->decimal('delivery_charge_refund_percentage', 5, 2)->default(100.00);
            $table->json('cancellation_refund_rules')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('refund_policies');
    }
}