<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-order currency snapshot -- foundation for genuine multi-currency
 * support. Additive and nullable: existing orders and every order for which
 * no country tax strategy applies keep `currency_code = null`, meaning
 * "display using the platform's global AppSetting currency", exactly the
 * current behavior. Only orders where a country strategy actually applied
 * (see TaxCalculationService::override()) get this populated, from that
 * country's own CountryTaxProfile.currency_code, so the order's own invoice
 * can render amounts in the currency it was actually charged in rather than
 * whatever the admin's global currency setting happens to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('tax_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
    }
};
