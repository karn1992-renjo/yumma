<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('channel', 16);
                $table->string('label');
                $table->string('group', 32)->default('general');
                $table->string('title')->nullable();
                $table->text('body');
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['group', 'channel']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
