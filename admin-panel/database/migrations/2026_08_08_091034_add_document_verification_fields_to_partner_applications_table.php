<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_applications', 'gstin_number')) {
                $table->string('gstin_number')->nullable()->after('gst_certificate');
            }
            if (! Schema::hasColumn('partner_applications', 'dob')) {
                $table->date('dob')->nullable()->after('license_number');
            }
            if (! Schema::hasColumn('partner_applications', 'document_verification')) {
                $table->json('document_verification')->nullable()->after('onboarding_meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('partner_applications', 'gstin_number') ? 'gstin_number' : null,
                Schema::hasColumn('partner_applications', 'dob') ? 'dob' : null,
                Schema::hasColumn('partner_applications', 'document_verification') ? 'document_verification' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
