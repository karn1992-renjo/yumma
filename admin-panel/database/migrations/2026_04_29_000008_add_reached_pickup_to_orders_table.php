<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'reached_at')) {
                $table->timestamp('reached_at')->nullable()->after('ready_at');
            }
        });

        if (Schema::hasColumn('orders', 'status')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','confirmed','preparing','ready_for_pickup','reached_pickup','picked_up','on_the_way','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending'");
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=off');

                $tableDefinition = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
                if (!empty($tableDefinition) && !empty($tableDefinition[0]->sql)) {
                    $createSql = $tableDefinition[0]->sql;
                    $newCreateSql = str_replace(
                        [
                            "('pending','confirmed','preparing','ready_for_pickup','picked_up','on_the_way','delivered','cancelled','refunded')",
                            "(\"pending\",\"confirmed\",\"preparing\",\"ready_for_pickup\",\"picked_up\",\"on_the_way\",\"delivered\",\"cancelled\",\"refunded\")",
                        ],
                        "('pending','confirmed','preparing','ready_for_pickup','reached_pickup','picked_up','on_the_way','delivered','cancelled','refunded')",
                        $createSql
                    );

                    if ($newCreateSql !== $createSql) {
                        DB::statement('ALTER TABLE orders RENAME TO orders_old');
                        DB::statement($newCreateSql);

                        $columns = array_map(function ($column) {
                            return '"' . $column->name . '"';
                        }, DB::select("PRAGMA table_info('orders_old')"));

                        $columnsList = implode(', ', $columns);
                        DB::statement("INSERT INTO orders ({$columnsList}) SELECT {$columnsList} FROM orders_old");

                        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='orders_old' AND sql IS NOT NULL");
                        foreach ($indexes as $index) {
                            $indexSql = str_replace('orders_old', 'orders', $index->sql);
                            DB::statement($indexSql);
                        }

                        DB::statement('DROP TABLE orders_old');
                    }
                }

                DB::statement('PRAGMA foreign_keys=on');
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'reached_at')) {
                $table->dropColumn('reached_at');
            }
        });

        if (Schema::hasColumn('orders', 'status')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','confirmed','preparing','ready_for_pickup','picked_up','on_the_way','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending'");
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=off');

                $tableDefinition = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
                if (!empty($tableDefinition) && !empty($tableDefinition[0]->sql)) {
                    $createSql = $tableDefinition[0]->sql;
                    $newCreateSql = str_replace(
                        [
                            "('pending','confirmed','preparing','ready_for_pickup','reached_pickup','picked_up','on_the_way','delivered','cancelled','refunded')",
                            "(\"pending\",\"confirmed\",\"preparing\",\"ready_for_pickup\",\"reached_pickup\",\"picked_up\",\"on_the_way\",\"delivered\",\"cancelled\",\"refunded\")",
                        ],
                        "('pending','confirmed','preparing','ready_for_pickup','picked_up','on_the_way','delivered','cancelled','refunded')",
                        $createSql
                    );

                    if ($newCreateSql !== $createSql) {
                        DB::statement('ALTER TABLE orders RENAME TO orders_old');
                        DB::statement($newCreateSql);

                        $columns = array_map(function ($column) {
                            return '"' . $column->name . '"';
                        }, DB::select("PRAGMA table_info('orders_old')"));

                        $columnsList = implode(', ', $columns);
                        DB::statement("INSERT INTO orders ({$columnsList}) SELECT {$columnsList} FROM orders_old");

                        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name='orders_old' AND sql IS NOT NULL");
                        foreach ($indexes as $index) {
                            $indexSql = str_replace('orders_old', 'orders', $index->sql);
                            DB::statement($indexSql);
                        }

                        DB::statement('DROP TABLE orders_old');
                    }
                }

                DB::statement('PRAGMA foreign_keys=on');
            }
        }
    }
};
