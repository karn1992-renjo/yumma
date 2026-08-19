<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_number')->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requester_role')->default('customer');
            $table->foreignId('restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->string('category')->default('general');
            $table->string('stage')->default('bot');
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolve_notes')->nullable();
            $table->unsignedTinyInteger('csat_rating')->nullable();
            $table->text('csat_comment')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['stage', 'status']);
            $table->index(['requester_role', 'user_id']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type')->default('customer');
            $table->string('message_type')->default('text');
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->migrateLegacyTickets();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');

        if (Schema::hasTable('support_tickets_legacy')) {
            Schema::rename('support_tickets_legacy', 'support_tickets');
        }

        if (Schema::hasTable('support_ticket_replies_legacy')) {
            Schema::rename('support_ticket_replies_legacy', 'support_ticket_replies');
        }
    }

    private function migrateLegacyTickets(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        $orderNumbers = DB::table('orders')->pluck('id', 'order_number');

        $tickets = DB::table('support_tickets')->orderBy('id')->get();

        foreach ($tickets as $ticket) {
            $stage = in_array($ticket->status, ['resolved', 'closed'], true) ? 'resolved' : 'human';

            $orderId = null;
            foreach ($orderNumbers as $orderNumber => $id) {
                if ($orderNumber !== '' && str_contains((string) $ticket->subject, (string) $orderNumber)) {
                    $orderId = $id;
                    break;
                }
            }

            $conversationId = DB::table('support_conversations')->insertGetId([
                'conversation_number' => $ticket->ticket_number ?: ('SUP-' . Str::upper(Str::random(8))),
                'order_id' => $orderId,
                'user_id' => $ticket->user_id,
                'requester_role' => $ticket->requester_role ?? 'customer',
                'restaurant_id' => $ticket->restaurant_id,
                'category' => $ticket->category ?? 'general',
                'stage' => $stage,
                'status' => $ticket->status ?? 'open',
                'priority' => $ticket->priority ?? 'medium',
                'assigned_to' => $ticket->assigned_to,
                'assigned_at' => $ticket->assigned_at,
                'resolved_at' => $ticket->resolved_at,
                'resolve_notes' => $ticket->resolve_notes,
                'last_message_at' => $ticket->updated_at,
                'deleted_at' => $ticket->deleted_at,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,
            ]);

            DB::table('support_messages')->insert([
                'conversation_id' => $conversationId,
                'sender_id' => $ticket->user_id,
                'sender_type' => $ticket->requester_role ?? 'customer',
                'message_type' => 'text',
                'message' => $ticket->description,
                'attachment_path' => $ticket->attachment,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->created_at,
            ]);

            if (Schema::hasTable('support_ticket_replies')) {
                $replies = DB::table('support_ticket_replies')
                    ->where('ticket_id', $ticket->id)
                    ->orderBy('id')
                    ->get();

                foreach ($replies as $reply) {
                    $senderType = $reply->is_system_message
                        ? 'system'
                        : ($reply->is_admin_reply ? 'admin' : ($ticket->requester_role ?? 'customer'));

                    DB::table('support_messages')->insert([
                        'conversation_id' => $conversationId,
                        'sender_id' => $reply->is_system_message ? null : $reply->user_id,
                        'sender_type' => $senderType,
                        'message_type' => $reply->is_system_message ? 'system' : 'text',
                        'message' => $reply->message,
                        'attachment_path' => $reply->attachment,
                        'created_at' => $reply->created_at,
                        'updated_at' => $reply->updated_at,
                    ]);
                }
            }
        }

        Schema::rename('support_tickets', 'support_tickets_legacy');

        if (Schema::hasTable('support_ticket_replies')) {
            Schema::rename('support_ticket_replies', 'support_ticket_replies_legacy');
        }
    }
};
