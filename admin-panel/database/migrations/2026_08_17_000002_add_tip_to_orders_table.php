<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tip_amount', 10, 2)->nullable()->default(0)->after('total');
            $table->timestamp('tip_paid_at')->nullable()->after('tip_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tip_amount', 'tip_paid_at']);
        });
    }
};
