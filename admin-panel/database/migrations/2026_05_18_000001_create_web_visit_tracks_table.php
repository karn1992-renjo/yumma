<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_visit_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 80)->nullable()->index();
            $table->string('source', 40)->default('web')->index();
            $table->string('panel', 40)->nullable()->index();
            $table->string('url', 2048);
            $table->string('path', 1024)->nullable()->index();
            $table->string('referrer', 2048)->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('country', 120)->nullable();
            $table->string('timezone', 120)->nullable()->index();
            $table->dateTime('local_time')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_visit_tracks');
    }
};
