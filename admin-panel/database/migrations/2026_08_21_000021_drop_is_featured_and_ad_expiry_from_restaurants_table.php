<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
            if (Schema::hasColumn('restaurants', 'ad_expiry')) {
                $table->dropColumn('ad_expiry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
            if (! Schema::hasColumn('restaurants', 'ad_expiry')) {
                $table->timestamp('ad_expiry')->nullable();
            }
        });
    }
};
