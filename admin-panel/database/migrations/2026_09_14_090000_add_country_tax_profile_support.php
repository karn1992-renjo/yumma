<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for country-based tax/compliance: a restaurant (or its branch)
 * can be tagged with an ISO country code, and each country gets its own
 * profile row (currency, tax label/rate, arbitrary strategy-specific config).
 *
 * Deliberately additive only -- no existing India GST/TCS/TDS columns or
 * AppSetting keys are touched. A restaurant/branch with no country_code set
 * resolves to `AppSetting.platform_country_code` (defaults 'IN'), which
 * preserves today's behaviour for every existing restaurant with zero data
 * migration needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('state_code');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('country');
            }
        });

        if (! Schema::hasTable('country_tax_profiles')) {
            Schema::create('country_tax_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('country_code', 2)->unique();
                $table->string('country_name', 80);
                $table->string('currency_code', 3);
                $table->string('tax_name', 40)->default('Tax');
                $table->boolean('is_active')->default(false)->index();
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_tax_profiles');

        if (Schema::hasColumn('branches', 'country_code')) {
            Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('country_code'));
        }
        if (Schema::hasColumn('restaurants', 'country_code')) {
            Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('country_code'));
        }
    }
};
