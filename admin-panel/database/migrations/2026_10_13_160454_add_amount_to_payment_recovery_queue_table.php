<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_recovery_queue', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('gateway_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payment_recovery_queue', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
