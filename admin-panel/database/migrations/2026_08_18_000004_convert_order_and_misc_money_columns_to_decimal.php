<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * (table, column, nullable) triples for columns still declared as a plain
     * integer at the DB level despite holding money values -- same defect as
     * payouts.amount (see 2026_08_18_000002_convert_payouts_amount_column_to_decimal.php).
     */
    private function columns(): array
    {
        return [
            ['orders', 'subtotal', false],
            ['orders', 'delivery_fee', false],
            ['orders', 'total', false],
            ['order_items', 'unit_price', false],
            ['order_items', 'total_price', false],
            ['restaurants', 'min_order_amount', false],
            ['restaurants', 'delivery_fee', false],
            ['gig_incentives', 'total_earned', false],
            ['delivery_charge_settings', 'free_delivery_threshold', true],
        ];
    }

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->columns() as [$table, $column, $nullable]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL(12,2) {$null}");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->columns() as [$table, $column, $nullable]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT {$null}");
        }
    }
};
