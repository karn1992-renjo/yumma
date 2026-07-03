<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_applications', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('license_document');
            }
            if (! Schema::hasColumn('partner_applications', 'vehicle_image')) {
                $table->string('vehicle_image')->nullable()->after('profile_photo');
            }
            if (! Schema::hasColumn('partner_applications', 'aadhar_card')) {
                $table->string('aadhar_card')->nullable()->after('vehicle_image');
            }
            if (! Schema::hasColumn('partner_applications', 'pan_card')) {
                $table->string('pan_card')->nullable()->after('aadhar_card');
            }
            if (! Schema::hasColumn('partner_applications', 'vehicle_rc')) {
                $table->string('vehicle_rc')->nullable()->after('pan_card');
            }
            if (! Schema::hasColumn('partner_applications', 'insurance_document')) {
                $table->string('insurance_document')->nullable()->after('vehicle_rc');
            }
            if (! Schema::hasColumn('partner_applications', 'onboarding_meta')) {
                $table->json('onboarding_meta')->nullable()->after('bank_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('partner_applications', 'profile_photo') ? 'profile_photo' : null,
                Schema::hasColumn('partner_applications', 'vehicle_image') ? 'vehicle_image' : null,
                Schema::hasColumn('partner_applications', 'aadhar_card') ? 'aadhar_card' : null,
                Schema::hasColumn('partner_applications', 'pan_card') ? 'pan_card' : null,
                Schema::hasColumn('partner_applications', 'vehicle_rc') ? 'vehicle_rc' : null,
                Schema::hasColumn('partner_applications', 'insurance_document') ? 'insurance_document' : null,
                Schema::hasColumn('partner_applications', 'onboarding_meta') ? 'onboarding_meta' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
