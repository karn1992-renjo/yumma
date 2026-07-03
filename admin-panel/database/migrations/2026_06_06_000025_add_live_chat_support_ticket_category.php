<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->enum('category', [
                'order_issue',
                'payment_issue',
                'technical_support',
                'account_issue',
                'general_inquiry',
                'live_chat',
            ])->default('general_inquiry')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->enum('category', [
                'order_issue',
                'payment_issue',
                'technical_support',
                'account_issue',
                'general_inquiry',
            ])->default('general_inquiry')->change();
        });
    }
};
