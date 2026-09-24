<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_counters')) {
            return;
        }

        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->string('series', 32);          // e.g. "INV" (invoice_number_prefix)
            $table->string('financial_year', 9);   // e.g. "2026-27"
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['series', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_counters');
    }
};
