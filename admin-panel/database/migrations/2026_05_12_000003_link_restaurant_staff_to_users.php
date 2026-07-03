<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_staff', function (Blueprint $table) {
            if (!Schema::hasColumn('restaurant_staff', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Linkage migration only. Intentionally non-destructive.
    }
};
