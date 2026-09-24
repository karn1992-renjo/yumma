<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theatres', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('aisle_handoff_only');
        });
    }

    public function down(): void
    {
        Schema::table('theatres', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};

