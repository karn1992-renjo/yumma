<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_ad_campaigns')) {
            Schema::create('restaurant_ad_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('status', 24)->default('draft');
                $table->decimal('max_cpc', 8, 2);
                $table->decimal('daily_budget', 10, 2)->nullable();
                $table->decimal('total_budget', 10, 2)->nullable();
                $table->decimal('spent_total', 10, 2)->default(0);
                $table->decimal('spent_today', 10, 2)->default(0);
                $table->date('starts_at');
                $table->date('ends_at')->nullable();
                $table->json('targeting')->nullable();
                $table->foreignId('admin_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('admin_reviewed_at')->nullable();
                $table->string('admin_review_note')->nullable();
                $table->string('rejected_reason')->nullable();
                $table->string('paused_reason')->nullable();
                $table->timestamps();

                $table->index(['status', 'restaurant_id']);
                $table->index(['status', 'starts_at', 'ends_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ad_campaigns');
    }
};
