<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_onboardings', function (Blueprint $table) {
            $table->id();
            $table->string('application_number');
            $table->foreignId('driver_id');
            $table->foreignId('partner_application_id')->nullable();
            $table->foreignId('restaurant_id')->nullable();
            $table->string('registration_source', 32)->default('driver');
            $table->string('status', 32)->default('draft');
            $table->decimal('incentive_amount', 12, 2)->default(0);
            $table->string('incentive_status', 32)->default('pending');
            $table->boolean('owner_mobile_verified')->default(false);
            $table->timestamp('owner_mobile_verified_at')->nullable();
            $table->decimal('driver_latitude', 10, 8)->nullable();
            $table->decimal('driver_longitude', 11, 8)->nullable();
            $table->decimal('restaurant_latitude', 10, 8)->nullable();
            $table->decimal('restaurant_longitude', 11, 8)->nullable();
            $table->decimal('distance_from_restaurant', 10, 3)->nullable();
            $table->timestamp('location_captured_at')->nullable();
            $table->json('draft_payload')->nullable();
            $table->json('duplicate_check')->nullable();
            $table->json('correction_fields')->nullable();
            $table->text('correction_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('onboarding_started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique('application_number', 'ro_application_number_unique');
            $table->index('status', 'ro_status_idx');
            $table->index('incentive_status', 'ro_incentive_status_idx');
            $table->index(['driver_id', 'status'], 'ro_driver_status_idx');
            $table->index(['partner_application_id', 'status'], 'ro_application_status_idx');
            $table->index(['restaurant_id', 'status'], 'ro_restaurant_status_idx');

            $table->foreign('driver_id', 'ro_driver_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('partner_application_id', 'ro_application_fk')->references('id')->on('partner_applications')->nullOnDelete();
            $table->foreign('restaurant_id', 'ro_restaurant_fk')->references('id')->on('restaurants')->nullOnDelete();
        });

        Schema::create('restaurant_onboarding_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_onboarding_id');
            $table->foreignId('driver_id');
            $table->foreignId('restaurant_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('earning_type', 64)->default('restaurant_onboarding_incentive');
            $table->foreignId('wallet_transaction_id')->nullable();
            $table->foreignId('payout_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_onboarding_id', 'earning_type'], 'roi_onboarding_type_unique');
            $table->index('status', 'roi_status_idx');
            $table->index(['driver_id', 'status'], 'roi_driver_status_idx');
            $table->index(['payout_id', 'status'], 'roi_payout_status_idx');

            $table->foreign('restaurant_onboarding_id', 'roi_onboarding_fk')->references('id')->on('restaurant_onboardings')->cascadeOnDelete();
            $table->foreign('driver_id', 'roi_driver_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('restaurant_id', 'roi_restaurant_fk')->references('id')->on('restaurants')->nullOnDelete();
            $table->foreign('wallet_transaction_id', 'roi_wallet_tx_fk')->references('id')->on('wallet_transactions')->nullOnDelete();
            $table->foreign('payout_id', 'roi_payout_fk')->references('id')->on('payouts')->nullOnDelete();
        });

        Schema::create('restaurant_onboarding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_onboarding_id');
            $table->foreignId('actor_id')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->string('event', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_onboarding_id', 'created_at'], 'roe_onboarding_created_idx');

            $table->foreign('restaurant_onboarding_id', 'roe_onboarding_fk')->references('id')->on('restaurant_onboardings')->cascadeOnDelete();
            $table->foreign('actor_id', 'roe_actor_fk')->references('id')->on('users')->nullOnDelete();
        });

        if (Schema::hasTable('app_settings')) {
            $defaults = [
                'driver_restaurant_onboarding_enabled' => '1',
                'driver_restaurant_onboarding_incentive_amount' => '300',
                'driver_restaurant_onboarding_incentive_trigger' => 'restaurant_activated',
                'driver_restaurant_onboarding_owner_otp' => 'required',
                'driver_restaurant_onboarding_gps_required' => '0',
                'driver_restaurant_onboarding_gps_radius_meters' => '200',
                'driver_restaurant_onboarding_daily_limit' => '',
                'driver_restaurant_onboarding_monthly_limit' => '',
                'driver_restaurant_onboarding_eligible_driver_ids' => '',
                'driver_restaurant_onboarding_eligible_zone_ids' => '',
                'driver_restaurant_onboarding_eligible_categories' => '',
            ];

            foreach ($defaults as $key => $value) {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_onboarding_events');
        Schema::dropIfExists('restaurant_onboarding_incentives');
        Schema::dropIfExists('restaurant_onboardings');
    }
};