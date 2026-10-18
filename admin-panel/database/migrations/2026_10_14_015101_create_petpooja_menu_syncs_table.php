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
        Schema::create('petpooja_menu_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('source', 10); // push|fetch
            $table->json('raw_payload');
            $table->unsignedInteger('item_count')->nullable();
            $table->unsignedInteger('category_count')->nullable();
            $table->string('status', 20)->default('received'); // received|applied|failed
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petpooja_menu_syncs');
    }
};
