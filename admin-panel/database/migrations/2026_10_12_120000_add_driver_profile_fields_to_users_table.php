<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('users', 'landmark')) {
                $table->string('landmark')->nullable()->after('city');
            }
            if (! Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('users', 'vehicle_model')) {
                $table->string('vehicle_model')->nullable()->after('vehicle_number');
            }
            if (! Schema::hasColumn('users', 'fuel_type')) {
                $table->string('fuel_type', 50)->nullable()->after('vehicle_model');
            }
            // Document paths captured during driver partner-application
            // onboarding (profile photo reuses the existing
            // profile_photo_path column shared with other roles).
            if (! Schema::hasColumn('users', 'vehicle_image')) {
                $table->string('vehicle_image')->nullable()->after('fuel_type');
            }
            if (! Schema::hasColumn('users', 'license_document')) {
                $table->string('license_document')->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('users', 'aadhar_card')) {
                $table->string('aadhar_card')->nullable();
            }
            if (! Schema::hasColumn('users', 'pan_card')) {
                $table->string('pan_card')->nullable();
            }
            if (! Schema::hasColumn('users', 'vehicle_rc')) {
                $table->string('vehicle_rc')->nullable();
            }
            if (! Schema::hasColumn('users', 'insurance_document')) {
                $table->string('insurance_document')->nullable();
            }
            if (! Schema::hasColumn('users', 'background_location_enabled')) {
                $table->boolean('background_location_enabled')->nullable();
            }
            if (! Schema::hasColumn('users', 'notification_permission_enabled')) {
                $table->boolean('notification_permission_enabled')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'city', 'landmark', 'date_of_birth', 'gender',
                'vehicle_model', 'fuel_type', 'vehicle_image', 'license_document',
                'aadhar_card', 'pan_card', 'vehicle_rc', 'insurance_document',
                'background_location_enabled', 'notification_permission_enabled',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
