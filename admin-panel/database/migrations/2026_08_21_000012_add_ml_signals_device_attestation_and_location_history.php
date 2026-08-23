<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gig_external_signals')) {
            Schema::create('gig_external_signals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();
                $table->date('date')->index();
                $table->unsignedTinyInteger('hour')->default(0);
                $table->string('source', 40)->index();
                $table->decimal('score', 8, 2)->default(0);
                $table->json('payload')->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
                $table->unique(['area_id', 'date', 'hour', 'source'], 'gig_external_signals_area_date_hour_source_unique');
            });
        }

        if (! Schema::hasTable('driver_location_events')) {
            Schema::create('driver_location_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('lat', 10, 8);
                $table->decimal('lng', 11, 8);
                $table->decimal('accuracy_meters', 10, 2)->nullable();
                $table->decimal('speed_mps', 10, 2)->nullable();
                $table->decimal('heading', 8, 2)->nullable();
                $table->boolean('is_mock_location')->default(false);
                $table->string('device_id', 120)->nullable()->index();
                $table->string('attestation_status', 32)->default('missing')->index();
                $table->unsignedSmallInteger('risk_score')->default(0);
                $table->json('risk_reasons')->nullable();
                $table->timestamp('recorded_at')->nullable()->index();
                $table->timestamps();
                $table->index(['driver_id', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('driver_device_attestations')) {
            Schema::create('driver_device_attestations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->string('device_id', 120)->nullable()->index();
                $table->string('platform', 32)->nullable();
                $table->string('provider', 40)->default('app');
                $table->string('status', 32)->default('missing')->index();
                $table->unsignedSmallInteger('risk_score')->default(0);
                $table->json('claims')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->index(['driver_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_device_attestations');
        Schema::dropIfExists('driver_location_events');
        Schema::dropIfExists('gig_external_signals');
    }
};