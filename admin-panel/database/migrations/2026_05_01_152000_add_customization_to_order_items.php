<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'selected_variant')) {
                $table->json('selected_variant')->nullable()->after('total_price');
            }
            if (!Schema::hasColumn('order_items', 'selected_add_ons')) {
                $table->json('selected_add_ons')->nullable()->after('selected_variant');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $columns = array_values(array_filter(['selected_variant', 'selected_add_ons'], fn ($column) => Schema::hasColumn('order_items', $column)));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
