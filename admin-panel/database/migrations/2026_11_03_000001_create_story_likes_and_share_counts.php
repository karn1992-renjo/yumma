<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurant_story_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_story_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('liked_at')->nullable();

            $table->unique(['restaurant_story_id', 'user_id']);
        });

        Schema::table('restaurant_stories', function (Blueprint $table) {
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
        });
    }

    public function down()
    {
        Schema::table('restaurant_stories', function (Blueprint $table) {
            $table->dropColumn(['like_count', 'share_count']);
        });
        Schema::dropIfExists('restaurant_story_likes');
    }
};
