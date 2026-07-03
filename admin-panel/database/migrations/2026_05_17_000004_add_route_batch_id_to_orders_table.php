<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'route_batch_id')) {
                $table->string('route_batch_id')->nullable()->after('driver_accepted_at');
                $table->index('route_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'route_batch_id')) {
                $table->dropIndex(['route_batch_id']);
                $table->dropColumn('route_batch_id');
            }
        });
    }
};
