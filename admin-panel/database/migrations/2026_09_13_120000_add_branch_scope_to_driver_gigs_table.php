<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_gigs', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('area_id')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('driver_gigs', 'open_to_pool')) {
                // Only meaningful when branch_id is set: false (default) restricts booking to
                // that branch's own drivers; true lets any platform driver book it too, for a
                // branch that's short-staffed. Admin-created gigs (branch_id null) are already
                // platform-wide regardless of this flag.
                $table->boolean('open_to_pool')->default(false)->after('branch_id');
            }
        });

        if (Schema::hasTable('gig_payout_approvals') && ! Schema::hasColumn('gig_payout_approvals', 'approved_via')) {
            Schema::table('gig_payout_approvals', function (Blueprint $table) {
                $table->string('approved_via', 20)->nullable()->after('approved_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gig_payout_approvals') && Schema::hasColumn('gig_payout_approvals', 'approved_via')) {
            Schema::table('gig_payout_approvals', function (Blueprint $table) {
                $table->dropColumn('approved_via');
            });
        }

        Schema::table('driver_gigs', function (Blueprint $table) {
            if (Schema::hasColumn('driver_gigs', 'open_to_pool')) {
                $table->dropColumn('open_to_pool');
            }
            if (Schema::hasColumn('driver_gigs', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }
        });
    }
};
