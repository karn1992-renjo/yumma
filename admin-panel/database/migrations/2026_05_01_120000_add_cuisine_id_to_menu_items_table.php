<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'cuisine_id')) {
                $table->foreignId('cuisine_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('cuisines')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('menu_items', 'cuisine_id')) {
                $table->dropConstrainedForeignId('cuisine_id');
            }
        });
    }
};
