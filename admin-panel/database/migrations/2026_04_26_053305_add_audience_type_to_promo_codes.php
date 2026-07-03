// database/migrations/2026_01_01_000012_add_audience_type_to_promo_codes.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('audience_type')->default('all')->after('max_discount_amount'); // all, new_customer, returning_customer
            $table->string('coupon_type')->default('public')->after('audience_type'); // public, prepaid
            $table->foreignId('assigned_to')->nullable()->after('coupon_type')->constrained('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn(['audience_type', 'coupon_type', 'assigned_to']);
        });
    }
};