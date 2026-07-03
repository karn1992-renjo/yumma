// database/migrations/2026_01_01_000011_create_campaigns_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // banner, popup, email, push
            $table->string('target_audience'); // all, new_customer, returning_customer
            $table->string('target_location')->nullable();
            $table->json('discount_details')->nullable();
            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
};