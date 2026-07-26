<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_scratch_cards', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_scratch_cards', 'metadata')) {
                $table->json('metadata')->nullable()->after('reward');
            }
            if (! Schema::hasColumn('customer_scratch_cards', 'viewed_at')) {
                $table->dateTime('viewed_at')->nullable()->after('issued_at');
            }
            if (! Schema::hasColumn('customer_scratch_cards', 'scratched_at')) {
                $table->dateTime('scratched_at')->nullable()->after('viewed_at');
            }
            if (! Schema::hasColumn('customer_scratch_cards', 'reward_generated_at')) {
                $table->dateTime('reward_generated_at')->nullable()->after('revealed_at');
            }
            if (! Schema::hasColumn('customer_scratch_cards', 'reward_credited_at')) {
                $table->dateTime('reward_credited_at')->nullable()->after('reward_generated_at');
            }
            if (! Schema::hasColumn('customer_scratch_cards', 'redeemed_at')) {
                $table->dateTime('redeemed_at')->nullable()->after('reward_credited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_scratch_cards', function (Blueprint $table) {
            foreach (['metadata', 'viewed_at', 'scratched_at', 'reward_generated_at', 'reward_credited_at', 'redeemed_at'] as $column) {
                if (Schema::hasColumn('customer_scratch_cards', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
