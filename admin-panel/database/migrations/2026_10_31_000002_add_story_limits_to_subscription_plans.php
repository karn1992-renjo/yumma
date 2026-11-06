<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // How many Stories a restaurant may post per day on this plan (NULL = unlimited).
            $table->unsignedSmallInteger('stories_per_day')->nullable()->after('menu_item_limit');
            // How long each Story stays live (NULL = the default 24 hours).
            $table->unsignedSmallInteger('story_duration_hours')->nullable()->after('stories_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['stories_per_day', 'story_duration_hours']);
        });
    }
};
