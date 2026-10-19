<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('splash_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('headline')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image_path');
            $table->string('logo_path')->nullable();
            $table->string('background_color')->nullable();
            $table->enum('platform', ['android', 'ios', 'both'])->default('both');
            $table->boolean('is_active')->default(false);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->unsignedInteger('display_duration_seconds')->default(4);
            $table->enum('tap_action', ['none', 'home', 'offers', 'restaurant', 'gold'])->default('none');
            $table->foreignId('tap_restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'platform', 'start_at', 'end_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('splash_campaigns');
    }
};
