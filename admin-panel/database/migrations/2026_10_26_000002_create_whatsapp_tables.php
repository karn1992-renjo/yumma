<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per WhatsApp number we have talked to: opt-in state, the 24h customer-service
        // window and the bot conversation state (used from Phase 3).
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();                 // normalized, +CCNNN
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_name')->nullable();
            $table->boolean('opted_in')->default(true);
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->string('bot_state', 60)->nullable();
            $table->json('bot_context')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('whatsapp_contacts')->nullOnDelete();
            $table->string('direction', 3);                          // in | out
            $table->string('provider', 20);
            $table->string('phone', 20)->index();
            $table->string('type', 20)->default('text');             // text | template | interactive | other
            $table->string('template')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status', 12)->default('queued');         // queued|sent|delivered|read|failed|received
            $table->string('error', 500)->nullable();
            $table->string('event')->nullable()->index();            // e.g. order_placed (Phase 2)
            $table->nullableMorphs('related');
            $table->timestamps();
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
