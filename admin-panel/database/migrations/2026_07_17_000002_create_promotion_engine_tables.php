<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type')->default('admin')->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('promotion_type')->default('percentage_discount')->index();
            $table->string('application_mode')->default('automatic')->index();
            $table->string('status')->default('draft')->index();
            $table->integer('priority')->default(100)->index();
            $table->boolean('is_exclusive')->default(false);
            $table->string('image_path')->nullable();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
            $table->json('schedule')->nullable();
            $table->json('targets')->nullable();
            $table->json('conditions')->nullable();
            $table->json('rewards')->nullable();
            $table->json('stacking')->nullable();
            $table->json('visibility')->nullable();
            $table->unsignedInteger('total_usage_limit')->nullable();
            $table->unsignedInteger('per_user_usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->string('migrated_from_type')->nullable()->index();
            $table->unsignedBigInteger('migrated_from_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['restaurant_id', 'status']);
            $table->index(['migrated_from_type', 'migrated_from_id'], 'promotions_migrated_source_index');
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('target_type')->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'target_type']);
        });

        Schema::create('promotion_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type')->index();
            $table->string('operator')->nullable();
            $table->json('value')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'condition_type']);
        });

        Schema::create('promotion_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type')->index();
            $table->decimal('value', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'reward_type']);
        });

        Schema::create('promotion_coupon_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'is_active']);
            $table->index(['code', 'is_active']);
        });

        Schema::create('promotion_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promotion_coupon_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('coupon_code')->nullable()->index();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('cashback_amount', 12, 2)->default(0);
            $table->json('discount_lines')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['promotion_id', 'user_id']);
            $table->index(['order_id', 'promotion_id']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->index();
            $table->string('coupon_code')->nullable()->index();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
        });

        $this->migrateLegacyPromos();
        $this->migrateLegacyCampaigns();
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_logs');
        Schema::dropIfExists('promotion_usage');
        Schema::dropIfExists('promotion_coupon_codes');
        Schema::dropIfExists('promotion_rewards');
        Schema::dropIfExists('promotion_conditions');
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }

    private function migrateLegacyPromos(): void
    {
        if (! Schema::hasTable('promo_codes')) {
            return;
        }

        DB::table('promo_codes')->orderBy('id')->chunkById(200, function ($promos) {
            foreach ($promos as $promo) {
                $promotionId = DB::table('promotions')->insertGetId([
                    'owner_type' => $promo->restaurant_id ? 'restaurant' : 'admin',
                    'owner_id' => $promo->restaurant_id,
                    'restaurant_id' => $promo->restaurant_id,
                    'title' => ($promo->title ?? null) ?: $promo->code,
                    'description' => $promo->description,
                    'promotion_type' => ($promo->discount_type ?? 'percentage') === 'percentage'
                        ? 'percentage_discount'
                        : 'flat_discount',
                    'application_mode' => 'coupon',
                    'status' => $promo->is_active ? 'active' : 'paused',
                    'priority' => 100,
                    'image_path' => $promo->promo_image ?? null,
                    'starts_at' => $promo->start_date ?? null,
                    'ends_at' => $promo->end_date ?? null,
                    'conditions' => json_encode([
                        'min_order_amount' => $promo->min_order_amount ?? null,
                        'audience_type' => $promo->audience_type ?? 'all',
                    ]),
                    'rewards' => json_encode([
                        'type' => ($promo->discount_type ?? 'percentage') === 'percentage' ? 'percentage' : 'flat',
                        'value' => (float) ($promo->discount_value ?? 0),
                        'max_discount' => $promo->max_discount_amount !== null ? (float) $promo->max_discount_amount : null,
                    ]),
                    'targets' => json_encode([
                        'restaurant_ids' => $promo->restaurant_id ? [(int) $promo->restaurant_id] : [],
                    ]),
                    'total_usage_limit' => $promo->usage_limit ?? null,
                    'used_count' => (int) ($promo->used_count ?? 0),
                    'migrated_from_type' => 'promo_code',
                    'migrated_from_id' => $promo->id,
                    'created_at' => $promo->created_at ?? now(),
                    'updated_at' => $promo->updated_at ?? now(),
                ]);

                DB::table('promotion_coupon_codes')->insert([
                    'promotion_id' => $promotionId,
                    'code' => $promo->code,
                    'user_id' => $promo->assigned_to ?? null,
                    'usage_limit' => $promo->usage_limit ?? null,
                    'used_count' => (int) ($promo->used_count ?? 0),
                    'is_active' => (bool) ($promo->is_active ?? true),
                    'starts_at' => $promo->start_date ?? null,
                    'ends_at' => $promo->end_date ?? null,
                    'metadata' => json_encode([
                        'legacy_coupon_type' => $promo->coupon_type ?? null,
                    ]),
                    'created_at' => $promo->created_at ?? now(),
                    'updated_at' => $promo->updated_at ?? now(),
                ]);
            }
        });
    }

    private function migrateLegacyCampaigns(): void
    {
        if (! Schema::hasTable('campaigns')) {
            return;
        }

        DB::table('campaigns')->orderBy('id')->chunkById(200, function ($campaigns) {
            foreach ($campaigns as $campaign) {
                DB::table('promotions')->insert([
                    'owner_type' => 'admin',
                    'title' => $campaign->name,
                    'description' => null,
                    'promotion_type' => 'campaign',
                    'application_mode' => 'automatic',
                    'status' => $campaign->is_active ? 'active' : 'paused',
                    'priority' => 200,
                    'image_path' => $campaign->image_url ?? null,
                    'starts_at' => $campaign->start_date ?? null,
                    'ends_at' => $campaign->end_date ?? null,
                    'targets' => json_encode([
                        'audience_type' => $campaign->target_audience ?? 'all',
                        'location' => $campaign->target_location ?? null,
                    ]),
                    'conditions' => json_encode([
                        'audience_type' => $campaign->target_audience ?? 'all',
                    ]),
                    'rewards' => $campaign->discount_details,
                    'visibility' => json_encode([
                        'campaign_type' => $campaign->type,
                        'link_url' => $campaign->link_url,
                    ]),
                    'migrated_from_type' => 'campaign',
                    'migrated_from_id' => $campaign->id,
                    'created_at' => $campaign->created_at ?? now(),
                    'updated_at' => $campaign->updated_at ?? now(),
                ]);
            }
        });
    }
};
