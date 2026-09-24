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
        Schema::create('call_masking_pool', function (Blueprint $table) {
            $table->id();
            $table->string('exophone', 32)->unique();
            $table->string('exotel_sid')->nullable();
            $table->enum('status', ['available', 'in_use', 'disabled'])->default('available');
            $table->foreignId('current_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_masking_pool');
    }
};
