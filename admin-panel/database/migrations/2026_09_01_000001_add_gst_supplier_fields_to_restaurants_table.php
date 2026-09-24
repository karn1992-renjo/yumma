<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurants')) {
            return;
        }

        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'is_gst_registered')) {
                $table->boolean('is_gst_registered')->default(false)->after('fssai_license_number');
            }
            if (! Schema::hasColumn('restaurants', 'gstin')) {
                $table->string('gstin', 20)->nullable()->after('is_gst_registered');
            }
            if (! Schema::hasColumn('restaurants', 'pan')) {
                $table->string('pan', 15)->nullable()->after('gstin');
            }
            if (! Schema::hasColumn('restaurants', 'state_code')) {
                // GST state code (2-digit) for place-of-supply on the invoice.
                $table->string('state_code', 2)->nullable()->after('state');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurants')) {
            return;
        }

        Schema::table('restaurants', function (Blueprint $table) {
            foreach (['is_gst_registered', 'gstin', 'pan', 'state_code'] as $column) {
                if (Schema::hasColumn('restaurants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
