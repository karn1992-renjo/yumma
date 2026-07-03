<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['gift_card_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_redemptions');
    }
};
