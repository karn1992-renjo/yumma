<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteMenuItemsTable();
            return;
        }

        DB::statement('ALTER TABLE menu_items MODIFY price DECIMAL(12,5) NOT NULL');
        DB::statement('ALTER TABLE menu_items MODIFY discounted_price DECIMAL(12,5) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu_items')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE menu_items MODIFY price INT NOT NULL');
        DB::statement('ALTER TABLE menu_items MODIFY discounted_price INT NULL');
    }

    private function rebuildSqliteMenuItemsTable(): void
    {
        $foreignKeys = DB::selectOne('PRAGMA foreign_keys');
        $foreignKeysEnabled = isset($foreignKeys->foreign_keys) && (int) $foreignKeys->foreign_keys === 1;

        if ($foreignKeysEnabled) {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            Schema::dropIfExists('menu_items_decimal_tmp');

            Schema::create('menu_items_decimal_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('cuisine_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 5);
                $table->decimal('discounted_price', 12, 5)->nullable();
                $table->json('images')->nullable();
                $table->boolean('is_veg')->default(true);
                $table->string('food_type')->default('veg');
                $table->boolean('is_available')->default(true);
                $table->boolean('is_recommended')->default(false);
                $table->boolean('is_bestseller')->default(false);
                $table->boolean('is_new')->default(false);
                $table->boolean('is_spicy')->default(false);
                $table->boolean('is_combo')->default(false);
                $table->integer('preparation_time')->default(15);
                $table->decimal('rating', 2, 1)->default(0);
                $table->integer('total_orders')->default(0);
                $table->json('tags')->nullable();
                $table->json('variants')->nullable();
                $table->json('add_ons')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'is_available']);
            });

            $columns = collect(Schema::getColumnListing('menu_items'));
            $targetColumns = collect([
                'id',
                'restaurant_id',
                'category_id',
                'cuisine_id',
                'name',
                'description',
                'price',
                'discounted_price',
                'images',
                'is_veg',
                'food_type',
                'is_available',
                'is_recommended',
                'is_bestseller',
                'is_new',
                'is_spicy',
                'is_combo',
                'preparation_time',
                'rating',
                'total_orders',
                'tags',
                'variants',
                'add_ons',
                'created_at',
                'updated_at',
            ])->filter(fn ($column) => $columns->contains($column))->values();

            $columnList = $targetColumns->implode(', ');
            DB::statement("INSERT INTO menu_items_decimal_tmp ({$columnList}) SELECT {$columnList} FROM menu_items");

            Schema::drop('menu_items');
            Schema::rename('menu_items_decimal_tmp', 'menu_items');
        } finally {
            if ($foreignKeysEnabled) {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }
};
