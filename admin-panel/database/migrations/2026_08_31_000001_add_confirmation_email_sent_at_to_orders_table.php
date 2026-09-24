<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'confirmation_email_sent_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Set the first time the customer order-confirmation email is
            // dispatched, so re-entrant OrderReleaseService::releaseToRestaurant
            // calls never send it twice.
            $table->timestamp('confirmation_email_sent_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'confirmation_email_sent_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('confirmation_email_sent_at');
            });
        }
    }
};
