<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'reward_points_balance')) {
                $table->unsignedBigInteger('reward_points_balance')->default(0)->after('is_active');
            }
        });

        Schema::create('scratch_card_reward_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scratch_card_id')->constrained('customer_scratch_cards')->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('reward_type')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'event_type']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('reward_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scratch_card_id')->nullable()->constrained('customer_scratch_cards')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('credit')->index();
            $table->unsignedInteger('points');
            $table->unsignedBigInteger('balance_after')->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scratch_card_id')->nullable()->constrained('customer_scratch_cards')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_coupon_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('gift_card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reward_type')->index();
            $table->string('status', 32)->default('issued')->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->string('code')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['scratch_card_id', 'reward_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('reward_point_transactions');
        Schema::dropIfExists('scratch_card_reward_logs');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reward_points_balance')) {
                $table->dropColumn('reward_points_balance');
            }
        });
    }
};
