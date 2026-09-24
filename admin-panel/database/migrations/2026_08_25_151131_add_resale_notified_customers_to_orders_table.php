<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'resale_notified_customer_ids')) {
                $table->text('resale_notified_customer_ids')->nullable()->after('resale_claimed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'resale_notified_customer_ids')) {
                $table->dropColumn('resale_notified_customer_ids');
            }
        });
    }
};
