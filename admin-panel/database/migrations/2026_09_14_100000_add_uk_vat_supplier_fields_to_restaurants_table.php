<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UK VAT registration fields on restaurants, named after UK terminology the
 * same way `gstin`/`is_gst_registered` are named after India's — additive
 * only, no existing column touched. A restaurant with no VAT registration
 * simply never gets VAT applied (UkVatTaxStrategy::appliesTo() gate),
 * exactly like an unregistered India restaurant never gets GST applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'is_vat_registered')) {
                $table->boolean('is_vat_registered')->default(false)->after('country_code');
            }
            if (! Schema::hasColumn('restaurants', 'vat_number')) {
                $table->string('vat_number', 20)->nullable()->after('is_vat_registered');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            foreach (['vat_number', 'is_vat_registered'] as $column) {
                if (Schema::hasColumn('restaurants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
