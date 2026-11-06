<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_open_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('audience', 20)->default('restaurant');       // which app plays it (restaurant app for now)
            $table->string('video_path');
            $table->unsignedSmallInteger('skip_after_seconds')->default(0);   // 0 = the Skip button shows straight away
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('app_open_video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_open_video_id')->constrained('app_open_videos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('skipped')->default(false);
            $table->timestamp('viewed_at');
            $table->unique(['app_open_video_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_open_video_views');
        Schema::dropIfExists('app_open_videos');
    }
};
