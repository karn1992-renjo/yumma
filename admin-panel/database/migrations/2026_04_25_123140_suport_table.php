<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->enum('category', ['order_issue', 'payment_issue', 'technical_support', 'account_issue', 'general_inquiry']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('description');
            $table->string('attachment')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->timestamps();
        });
        
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};