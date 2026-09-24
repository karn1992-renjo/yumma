<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ad_fraud_signals')) {
            Schema::create('ad_fraud_signals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_ad_campaign_id')->constrained('restaurant_ad_campaigns')->cascadeOnDelete();
                $table->foreignId('ad_click_id')->nullable()->constrained('ad_clicks')->nullOnDelete();
                $table->string('signal_type', 80);
                $table->string('severity', 24)->default('medium')->index();
                $table->unsignedSmallInteger('score')->default(0);
                $table->string('status', 24)->default('open')->index();
                $table->json('evidence')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['restaurant_ad_campaign_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_fraud_signals');
    }
};
