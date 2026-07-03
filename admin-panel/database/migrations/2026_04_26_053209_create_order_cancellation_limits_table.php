// database/migrations/2026_01_01_000009_create_order_cancellation_limits_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_cancellation_limits', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // restaurant, delivery_partner
            $table->decimal('warning_threshold', 5, 2)->default(20.00);
            $table->decimal('penalty_threshold', 5, 2)->default(30.00);
            $table->decimal('penalty_amount', 10, 2)->default(100.00);
            $table->boolean('auto_disable')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_cancellation_limits');
    }
};