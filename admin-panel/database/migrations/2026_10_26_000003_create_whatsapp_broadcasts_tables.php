<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('template');                       // provider template identifier
            $table->json('params')->nullable();               // ordered variables; {name} = customer's first name
            $table->string('audience', 30);                   // contacts | recent_customers | gold
            $table->unsignedSmallInteger('audience_days')->nullable();
            $table->string('status', 12)->default('draft');   // draft | sending | done | cancelled
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('whatsapp_broadcasts')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('first_name')->nullable();
            $table->string('status', 10)->default('pending'); // pending | sent | failed | skipped
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->unique(['broadcast_id', 'phone']);
            $table->index(['broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
        Schema::dropIfExists('whatsapp_broadcasts');
    }
};
