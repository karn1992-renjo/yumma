<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voice_ai_sessions')) {
            return;
        }

        Schema::create('voice_ai_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->unique();
            $table->string('provider', 40)->nullable();
            $table->string('fallback_provider', 40)->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('input_audio_seconds')->default(0);
            $table->unsignedInteger('output_audio_seconds')->default(0);
            $table->unsignedInteger('tool_calls_count')->default(0);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('started');
            $table->string('failure_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_ai_sessions');
    }
};
