<?php
// database/migrations/2024_01_01_000000_create_order_activities_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderActivitiesTable extends Migration
{
    public function up()
    {
        Schema::create('order_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_activities');
    }
}