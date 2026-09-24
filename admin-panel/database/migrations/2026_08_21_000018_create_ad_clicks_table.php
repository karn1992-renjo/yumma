<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_clicks')) {
            Schema::create('ad_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_ad_campaign_id')->constrained('restaurant_ad_campaigns')->cascadeOnDelete();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('session_id')->nullable();
                $table->string('surface', 32);
                $table->decimal('price_paid', 8, 2)->default(0);
                $table->decimal('rank_at_serve', 10, 4)->nullable();
                $table->decimal('competitor_rank_used', 10, 4)->nullable();
                $table->foreignId('restaurant_ad_wallet_transaction_id')->nullable()
                    ->constrained('restaurant_ad_wallet_transactions')->nullOnDelete();
                $table->boolean('is_billed')->default(false);
                $table->string('ip_address', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['restaurant_ad_campaign_id', 'user_id', 'created_at']);
                $table->index(['restaurant_ad_campaign_id', 'is_billed']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
    }
};
