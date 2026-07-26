<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_source')) {
                $table->string('payment_source', 32)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('orders', 'payment_gateway')) {
                $table->string('payment_gateway', 32)->nullable()->after('payment_source');
            }
            if (! Schema::hasColumn('orders', 'payment_link_id')) {
                $table->string('payment_link_id')->nullable()->after('payment_id');
            }
            if (! Schema::hasColumn('orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('online_payment_verified_at');
            }
            if (! Schema::hasColumn('orders', 'payment_attempts_count')) {
                $table->unsignedInteger('payment_attempts_count')->default(0)->after('payment_link_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['payment_source', 'payment_gateway', 'payment_link_id', 'paid_at', 'payment_attempts_count'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
