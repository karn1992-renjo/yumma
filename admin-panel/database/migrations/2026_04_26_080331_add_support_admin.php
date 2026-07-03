<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolve_notes')->nullable();
            $table->softDeletes();
        });
        
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->boolean('is_admin_reply')->default(false);
            $table->boolean('is_system_message')->default(false);
        });
    }
    
    public function down()
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['assigned_to', 'assigned_at', 'resolved_at', 'resolve_notes', 'deleted_at']);
        });
        
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropColumn(['is_admin_reply', 'is_system_message']);
        });
    }
};