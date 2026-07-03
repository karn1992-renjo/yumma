// database/migrations/2026_01_01_000010_create_gig_incentives_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gig_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_gig_id')->constrained()->onDelete('cascade');
            $table->integer('base_pay')->default(0);
            $table->integer('order_incentive')->default(0);
            $table->integer('active_time_incentive')->default(0);
            $table->integer('total_earned')->default(0);
            $table->json('orders_completed')->nullable();
            $table->integer('active_minutes')->default(0);
            $table->boolean('is_penalty_applied')->default(false);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->string('penalty_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gig_incentives');
    }
};