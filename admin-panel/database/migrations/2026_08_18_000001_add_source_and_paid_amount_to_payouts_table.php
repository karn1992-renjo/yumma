<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasColumn('payouts', 'source')) {
                $table->string('source', 32)->nullable()->after('idempotency_key');
            }
            if (! Schema::hasColumn('payouts', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('net_amount');
            }
        });

        DB::table('payouts')->where('idempotency_key', 'like', 'scheduled_%')->update(['source' => 'scheduled']);
        DB::table('payouts')->where('idempotency_key', 'like', 'manual_admin_%')->update(['source' => 'manual_admin']);
        DB::table('payouts')->where('idempotency_key', 'like', 'manual_driver_%')->update(['source' => 'manual_driver']);
        DB::table('payouts')->where('idempotency_key', 'like', 'manual_restaurant_%')->update(['source' => 'manual_restaurant']);
        DB::table('payouts')->where('idempotency_key', 'like', 'manual_legacy_%')->update(['source' => 'legacy']);

        DB::table('payouts')->where('status', 'completed')->update(['paid_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $columns = array_filter(['source', 'paid_amount'], fn ($column) => Schema::hasColumn('payouts', $column));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
