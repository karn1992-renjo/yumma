<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurants', 'dining_charge')) {
                $table->decimal('dining_charge', 10, 2)->default(0)->after('restaurant_type');
            }

            if (!Schema::hasColumn('restaurants', 'dining_settings')) {
                $table->json('dining_settings')->nullable()->after('dining_charge');
            }
        });

        Schema::table('dining_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('dining_bookings', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('cancellation_reason');
            }

            if (!Schema::hasColumn('dining_bookings', 'feedback')) {
                $table->text('feedback')->nullable()->after('rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dining_bookings', function (Blueprint $table) {
            $columns = array_values(array_filter(['feedback', 'rating'], fn ($column) => Schema::hasColumn('dining_bookings', $column)));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $columns = array_values(array_filter(['dining_settings'], fn ($column) => Schema::hasColumn('restaurants', $column)));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
