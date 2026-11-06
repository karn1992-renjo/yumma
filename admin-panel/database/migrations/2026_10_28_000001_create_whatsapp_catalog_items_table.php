<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which menu items exist in the WhatsApp (Meta) catalog, and what we last sent for each one,
        // so a sync only sends what changed and removes what is no longer sold.
        Schema::create('whatsapp_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id')->unique();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->string('retailer_id', 40)->unique();       // "mi_123": the id used inside WhatsApp carts
            $table->string('content_hash', 40);
            $table->string('status', 10)->default('synced');   // synced | failed | removed
            $table->string('error', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_catalog_items');
    }
};
