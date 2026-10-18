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
        Schema::table('restaurants', function (Blueprint $table) {
            // Reuses the existing `is_open` boolean as the actual
            // open/closed flag (already manually set elsewhere, not
            // cron-driven, so nothing else competes for control of it).
            // This column only carries the Petpooja-requested reopen
            // deadline for the scheduler to act on.
            $table->timestamp('petpooja_store_closed_until')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('petpooja_store_closed_until');
        });
    }
};
