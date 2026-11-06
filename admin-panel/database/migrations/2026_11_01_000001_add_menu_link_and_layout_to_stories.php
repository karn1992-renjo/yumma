<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_stories', function (Blueprint $table) {
            // A dish shown on the story with a button that opens it in the customer app.
            $table->unsignedBigInteger('menu_item_id')->nullable()->after('restaurant_id')->index();
            $table->string('cta_label', 30)->nullable()->after('caption');
            // How the story is laid out: classic | spotlight | split | poster.
            $table->string('layout', 16)->default('classic')->after('cta_label');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            // Whether a restaurant on this plan may link a menu item (with a button) to its stories.
            $table->boolean('story_menu_link')->default(false)->after('story_duration_hours');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_stories', function (Blueprint $table) {
            $table->dropColumn(['menu_item_id', 'cta_label', 'layout']);
        });
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('story_menu_link');
        });
    }
};
