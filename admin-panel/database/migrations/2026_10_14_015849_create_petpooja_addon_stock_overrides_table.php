<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('petpooja_addon_stock_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('petpooja_addon_id')->index();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('unavailable_until')->nullable()->index();
            $table->timestamps();

            // Explicit short name -- the auto-generated one exceeds
            // MySQL's 64-char identifier limit (SQLite doesn't enforce
            // this, which is why it passed local testing but failed on
            // production MySQL).
            $table->unique(['restaurant_id', 'petpooja_addon_id'], 'petpooja_addon_overrides_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petpooja_addon_stock_overrides');
    }
};
