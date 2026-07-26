<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 32)->nullable()->after('reward_points_balance')->unique();
            }

            if (! Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->foreignId('referred_by_user_id')
                    ->nullable()
                    ->after('referral_code')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'referral_registered_at')) {
                $table->timestamp('referral_registered_at')->nullable()->after('referred_by_user_id');
            }
        });

        DB::table('users')
            ->whereNull('referral_code')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->whereNull('referral_code')
                        ->update(['referral_code' => $this->referralCodeFor((int) $user->id)]);
                }
            });

        if (! Schema::hasTable('user_referrals')) {
            Schema::create('user_referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('referral_code', 32)->index();
                $table->string('status', 32)->default('registered')->index();
                $table->foreignId('qualified_order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('referrer_reward_redemption_id')->nullable()->constrained('reward_redemptions')->nullOnDelete();
                $table->foreignId('referred_reward_redemption_id')->nullable()->constrained('reward_redemptions')->nullOnDelete();
                $table->string('bonus_type', 40)->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->unsignedInteger('points')->nullable();
                $table->timestamp('qualified_at')->nullable();
                $table->timestamp('credited_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['referrer_id', 'status']);
                $table->index(['referred_user_id', 'status']);
            });
        }

        Schema::table('reward_redemptions', function (Blueprint $table) {
            if (! Schema::hasColumn('reward_redemptions', 'settlement_key')) {
                $table->string('settlement_key', 191)->nullable()->after('code')->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reward_redemptions', function (Blueprint $table) {
            if (Schema::hasColumn('reward_redemptions', 'settlement_key')) {
                $table->dropUnique(['settlement_key']);
                $table->dropColumn('settlement_key');
            }
        });

        Schema::dropIfExists('user_referrals');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by_user_id')) {
                $table->dropConstrainedForeignId('referred_by_user_id');
            }

            if (Schema::hasColumn('users', 'referral_registered_at')) {
                $table->dropColumn('referral_registered_at');
            }

            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropUnique(['referral_code']);
                $table->dropColumn('referral_code');
            }
        });
    }

    private function referralCodeFor(int $userId): string
    {
        return 'SWD' . str_pad(strtoupper(base_convert((string) $userId, 10, 36)), 6, '0', STR_PAD_LEFT);
    }
};
