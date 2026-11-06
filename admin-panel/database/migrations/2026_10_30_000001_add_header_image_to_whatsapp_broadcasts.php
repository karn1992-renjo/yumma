<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // For templates approved with an IMAGE header: the picture sent with every message.
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->string('header_image', 500)->nullable()->after('params');
            $table->json('roles')->nullable()->after('audience_days');   // limit any audience to these roles
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['header_image', 'roles']);
        });
    }
};
