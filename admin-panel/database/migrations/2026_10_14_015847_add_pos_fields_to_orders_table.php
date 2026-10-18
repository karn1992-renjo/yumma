<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pos_provider')->nullable()->index(); // e.g. 'petpooja'
            $table->string('pos_order_id')->nullable(); // the POS's own order id -- distinct from ours
            $table->string('pos_sync_status')->nullable(); // pending|sent|acknowledged|failed
            $table->text('pos_sync_error')->nullable();
            $table->timestamp('pos_last_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pos_provider', 'pos_order_id', 'pos_sync_status', 'pos_sync_error', 'pos_last_synced_at']);
        });
    }
};
