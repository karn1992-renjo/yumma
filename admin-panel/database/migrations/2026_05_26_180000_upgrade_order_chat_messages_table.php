<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_chat_messages', function (Blueprint $table) {
            $table->string('message_type', 30)->default('text')->after('recipient_role');
            $table->string('attachment_path')->nullable()->after('message');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->json('meta')->nullable()->after('attachment_size');
            $table->timestamp('delivered_at')->nullable()->after('meta');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_chat_messages', function (Blueprint $table) {
            $table->dropColumn([
                'message_type',
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
                'meta',
                'delivered_at',
                'read_at',
            ]);
        });
    }
};
