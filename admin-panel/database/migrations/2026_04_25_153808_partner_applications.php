<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->enum('partner_type', ['restaurant', 'driver']);
            
            // Common fields
            $table->string('city')->nullable();
            $table->string('city_id')->nullable();
            $table->text('address')->nullable();
            $table->text('bank_details')->nullable();
            
            // Restaurant specific fields
            $table->string('business_name')->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->string('pincode')->nullable();
            $table->json('cuisine')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_designation')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('gst_certificate')->nullable();
            $table->string('fssai_license')->nullable();
            
            // Driver specific fields
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('license_document')->nullable();
            
            // Common authentication
            $table->string('password')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('partner_type');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_applications');
    }
};