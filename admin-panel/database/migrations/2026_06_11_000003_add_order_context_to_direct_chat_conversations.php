<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_chat_conversations', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('title')
                ->constrained('orders')
                ->nullOnDelete();
            $table->string('context_type', 30)->default('direct')->after('order_id');

            $table->index(['order_id', 'context_type']);
        });
    }

    public function down(): void
    {
        Schema::table('direct_chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id', 'context_type']);
            $table->dropColumn(['order_id', 'context_type']);
        });
    }
};
