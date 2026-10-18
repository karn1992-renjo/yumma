<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurant_story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_story_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // Nullable at the DB level for the same MySQL-strict-mode reason
            // as restaurant_stories.posted_at/expires_at -- always set by the
            // application.
            $table->timestamp('viewed_at')->nullable();

            $table->unique(['restaurant_story_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('restaurant_story_views');
    }
};
