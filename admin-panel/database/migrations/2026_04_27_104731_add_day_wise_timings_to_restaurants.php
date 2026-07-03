<?php
// database/migrations/2026_01_01_000000_add_day_wise_timings_to_restaurants.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDayWiseTimingsToRestaurants extends Migration
{
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Store day-wise timings as JSON
            $table->json('weekly_timings')->nullable()->after('close_time');
            // Optional: Add timezone support
            $table->string('timezone')->default('Asia/Kolkata')->after('weekly_timings');
        });
    }

    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['weekly_timings', 'timezone']);
        });
    }
}