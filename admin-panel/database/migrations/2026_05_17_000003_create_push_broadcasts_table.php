<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('body', 500);
            $table->string('audience_type', 20)->default('all');
            $table->json('audience_roles')->nullable();
            $table->string('deep_link')->nullable();
            $table->json('data_payload')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_broadcasts');
    }
};
