<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('restaurants', 'auto_print_new_orders')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->boolean('auto_print_new_orders')
                    ->default(false)
                    ->after('auto_accept_orders');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurants', 'auto_print_new_orders')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropColumn('auto_print_new_orders');
            });
        }
    }
};
