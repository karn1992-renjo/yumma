<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_ad_wallets')) {
            Schema::create('restaurant_ad_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('currency', 8)->default('INR');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_ad_wallets');
    }
};
