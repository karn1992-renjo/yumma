<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->orderLifecycleColumns() as $column => $definition) {
            if (Schema::hasColumn('orders', $column)) {
                continue;
            }

            Schema::table('orders', function (Blueprint $table) use ($column, $definition) {
                $definition($table, $column);
            });
        }
    }

    public function down(): void
    {
        // Intentionally left blank. This migration is a production schema guard
        // for databases that missed earlier order lifecycle migrations.
    }

    private function orderLifecycleColumns(): array
    {
        return [
            'confirmed_at' => fn (Blueprint $table, string $column) => $table->timestamp($column)->nullable(),
            'preparation_time_minutes' => fn (Blueprint $table, string $column) => $table->unsignedSmallInteger($column)->nullable(),
            'preparing_at' => fn (Blueprint $table, string $column) => $table->timestamp($column)->nullable(),
            'ready_at' => fn (Blueprint $table, string $column) => $table->timestamp($column)->nullable(),
            'cancelled_at' => fn (Blueprint $table, string $column) => $table->timestamp($column)->nullable(),
            'cancellation_reason' => fn (Blueprint $table, string $column) => $table->text($column)->nullable(),
            'delivery_otp' => fn (Blueprint $table, string $column) => $table->unsignedSmallInteger($column)->nullable(),
        ];
    }
};
