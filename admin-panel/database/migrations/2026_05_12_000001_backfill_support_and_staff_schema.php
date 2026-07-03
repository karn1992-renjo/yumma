<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_staff')) {
            Schema::create('restaurant_staff', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('phone', 20)->nullable();
                $table->string('email')->nullable();
                $table->string('role')->default('staff');
                $table->string('shift')->nullable();
                $table->decimal('salary', 10, 2)->nullable();
                $table->json('permissions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['restaurant_id', 'is_active']);
                $table->index(['restaurant_id', 'role']);
            });
        }

        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ticket_number')->unique();
                $table->string('subject');
                $table->string('category')->default('general_inquiry');
                $table->string('priority')->default('medium');
                $table->text('description');
                $table->string('attachment')->nullable();
                $table->string('status')->default('open');
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolve_notes')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        } else {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (!Schema::hasColumn('support_tickets', 'restaurant_id')) {
                    $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
                }

                if (!Schema::hasColumn('support_tickets', 'assigned_to')) {
                    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('support_tickets', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable();
                }

                if (!Schema::hasColumn('support_tickets', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }

                if (!Schema::hasColumn('support_tickets', 'resolve_notes')) {
                    $table->text('resolve_notes')->nullable();
                }

                if (!Schema::hasColumn('support_tickets', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (!Schema::hasTable('support_ticket_replies')) {
            Schema::create('support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('support_tickets')->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('message');
                $table->string('attachment')->nullable();
                $table->boolean('is_admin_reply')->default(false);
                $table->boolean('is_system_message')->default(false);
                $table->timestamps();
            });
        } else {
            Schema::table('support_ticket_replies', function (Blueprint $table) {
                if (!Schema::hasColumn('support_ticket_replies', 'is_admin_reply')) {
                    $table->boolean('is_admin_reply')->default(false);
                }

                if (!Schema::hasColumn('support_ticket_replies', 'is_system_message')) {
                    $table->boolean('is_system_message')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        // Catch-up migration only. Intentionally left non-destructive.
    }
};
