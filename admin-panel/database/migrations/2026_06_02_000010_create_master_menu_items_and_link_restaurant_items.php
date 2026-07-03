<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category_name')->nullable();
            $table->string('subcategory_name')->nullable();
            $table->text('description')->nullable();
            $table->string('food_type', 20)->default('veg');
            $table->json('images')->nullable();
            $table->integer('preparation_time')->nullable();
            $table->decimal('gst', 5, 2)->nullable();
            $table->string('hsn_code', 50)->nullable();
            $table->json('variants')->nullable();
            $table->json('add_ons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category_name']);
            $table->index('food_type');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'master_menu_item_id')) {
                $table->foreignId('master_menu_item_id')
                    ->nullable()
                    ->after('restaurant_id')
                    ->constrained('master_menu_items')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('menu_items', 'item_source')) {
                $table->string('item_source', 20)->default('custom')->after('master_menu_item_id');
            }

            if (!Schema::hasColumn('menu_items', 'availability_schedule')) {
                $table->json('availability_schedule')->nullable()->after('is_available');
            }

            if (!Schema::hasColumn('menu_items', 'approval_status')) {
                $table->string('approval_status', 20)->default('approved')->after('availability_schedule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            foreach (['master_menu_item_id', 'item_source', 'availability_schedule', 'approval_status'] as $column) {
                if (Schema::hasColumn('menu_items', $column)) {
                    if ($column === 'master_menu_item_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });

        Schema::dropIfExists('master_menu_items');
    }
};
