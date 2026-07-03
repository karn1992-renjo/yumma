<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
                $table->integer('quantity');
                $table->integer('unit_price');
                $table->integer('total_price');
                $table->json('selected_variant')->nullable();
                $table->json('selected_add_ons')->nullable();
                $table->text('special_instructions')->nullable();
                $table->timestamps();
            });

            return;
        }

        $this->ensureCustomizationColumnsExist();
        $this->repairSqliteForeignKeyIfNeeded();
    }

    public function down(): void
    {
        // Repair migration only. Intentionally non-destructive.
    }

    private function ensureCustomizationColumnsExist(): void
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

    private function repairSqliteForeignKeyIfNeeded(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $tableDefinition = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'order_items'");
        $createSql = $tableDefinition->sql ?? null;

        if (!$createSql || !str_contains($createSql, 'orders_old')) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=off');
        DB::beginTransaction();

        try {
            DB::statement('ALTER TABLE order_items RENAME TO order_items_old');

            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->onDelete('cascade');
                $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
                $table->integer('quantity');
                $table->integer('unit_price');
                $table->integer('total_price');
                $table->json('selected_variant')->nullable();
                $table->json('selected_add_ons')->nullable();
                $table->text('special_instructions')->nullable();
                $table->timestamps();
            });

            $oldColumns = collect(DB::select("PRAGMA table_info('order_items_old')"))
                ->pluck('name')
                ->all();

            $copyColumns = array_values(array_intersect([
                'id',
                'order_id',
                'menu_item_id',
                'quantity',
                'unit_price',
                'total_price',
                'selected_variant',
                'selected_add_ons',
                'special_instructions',
                'created_at',
                'updated_at',
            ], $oldColumns));

            if ($copyColumns !== []) {
                $columnList = implode(', ', array_map(fn ($column) => "\"{$column}\"", $copyColumns));
                DB::statement("INSERT INTO order_items ({$columnList}) SELECT {$columnList} FROM order_items_old");
            }

            DB::statement('DROP TABLE order_items_old');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            DB::statement('PRAGMA foreign_keys=on');
            throw $e;
        }

        DB::statement('PRAGMA foreign_keys=on');
    }
};
