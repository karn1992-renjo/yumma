<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * swaad-only: lets a theatre owner create their OWN restaurant (a
 * concession stall) instead of only mapping an existing one -- mirrors
 * branch_restaurants' approval_status/approved_by/approved_at exactly
 * (see 2026_06_12_000001_create_branch_management_module.php). Default is
 * 'approved' (not 'theatre_pending') so every pre-existing mapped-restaurant
 * row is unaffected; only the new "create my own restaurant" flow ever
 * writes 'theatre_pending'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theatre_restaurants', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->index()->after('is_active');
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('theatre_restaurants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approved_at']);
        });
    }
};
