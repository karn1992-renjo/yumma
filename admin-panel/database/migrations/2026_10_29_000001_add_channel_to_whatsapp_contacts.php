<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Which WhatsApp number (provider) the customer last wrote to: free-form replies go back through it.
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('channel', 30)->nullable()->after('profile_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
