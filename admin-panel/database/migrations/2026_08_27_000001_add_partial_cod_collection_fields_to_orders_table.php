<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cod_collected_from_driver_amount')) {
                $table->decimal('cod_collected_from_driver_amount', 12, 2)->default(0)->after('cash_collected_at');
            }

            if (! Schema::hasColumn('orders', 'cod_last_collected_at')) {
                $table->timestamp('cod_last_collected_at')->nullable()->after('cod_collected_from_driver_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('orders', 'cod_last_collected_at') ? 'cod_last_collected_at' : null,
                Schema::hasColumn('orders', 'cod_collected_from_driver_amount') ? 'cod_collected_from_driver_amount' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
