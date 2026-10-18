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
        Schema::create('petpooja_restaurant_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->onDelete('cascade');
            $table->string('rest_id'); // Petpooja's own restaurant/mapping id
            $table->text('app_key_encrypted')->nullable();
            $table->text('app_secret_encrypted')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->string('environment', 20)->default('sandbox'); // sandbox|production
            $table->boolean('is_active')->default(false); // gates relay/sync
            $table->boolean('is_menu_managed_by_pos')->default(true); // read-only guard in our own menu editors
            // Reserved for when Petpooja confirms a real inbound-webhook auth scheme
            // (none is documented today -- only a restID field).
            $table->string('webhook_shared_secret')->nullable();
            $table->timestamp('last_menu_synced_at')->nullable();
            $table->string('last_menu_sync_status')->nullable();
            $table->text('last_menu_sync_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petpooja_restaurant_configs');
    }
};
