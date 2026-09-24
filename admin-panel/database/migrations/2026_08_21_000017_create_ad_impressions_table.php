<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_impressions')) {
            Schema::create('ad_impressions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_ad_campaign_id')->constrained('restaurant_ad_campaigns')->cascadeOnDelete();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('session_id')->nullable();
                $table->string('surface', 32);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['restaurant_ad_campaign_id', 'created_at']);
                $table->index(['surface', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_impressions');
    }
};
