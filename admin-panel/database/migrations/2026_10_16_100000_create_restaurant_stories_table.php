<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurant_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('media_path');
            $table->enum('media_type', ['image', 'video']);
            // Required for video (client-captured frame, since no server-side
            // video pipeline exists in this codebase to extract one) -- null
            // for images, where the image itself serves as its own thumbnail.
            $table->string('thumbnail_path')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // Nullable at the DB level only because MySQL strict mode (unlike
            // SQLite, used locally) rejects a non-default TIMESTAMP column
            // placed after other non-default TIMESTAMP columns -- the
            // application always sets both of these on create().
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['restaurant_id', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('restaurant_stories');
    }
};
