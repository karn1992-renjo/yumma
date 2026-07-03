<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('title')->nullable()->after('code');
            $table->string('promo_image')->nullable()->after('description');
            $table->string('created_by_type')->default('restaurant')->after('assigned_to');
        });

        try {
            Schema::table('promo_codes', function (Blueprint $table) {
                $table->dropForeign(['restaurant_id']);
            });
        } catch (\Throwable $e) {
            // Some installs may not have the conventional FK name.
        }

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable()->change();
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->index(['created_by_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropIndex(['created_by_type', 'is_active']);
            $table->dropForeign(['restaurant_id']);
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('restaurant_id')->nullable(false)->change();
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->dropColumn(['title', 'promo_image', 'created_by_type']);
        });
    }
};
