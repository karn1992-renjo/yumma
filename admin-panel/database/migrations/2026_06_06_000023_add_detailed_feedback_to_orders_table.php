<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'item_rating')) {
                $table->unsignedTinyInteger('item_rating')->nullable()->after('driver_rating');
            }
            if (! Schema::hasColumn('orders', 'service_rating')) {
                $table->unsignedTinyInteger('service_rating')->nullable()->after('item_rating');
            }
            if (! Schema::hasColumn('orders', 'item_feedback')) {
                $table->text('item_feedback')->nullable()->after('driver_feedback');
            }
            if (! Schema::hasColumn('orders', 'service_feedback')) {
                $table->text('service_feedback')->nullable()->after('item_feedback');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['service_feedback', 'item_feedback', 'service_rating', 'item_rating'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
