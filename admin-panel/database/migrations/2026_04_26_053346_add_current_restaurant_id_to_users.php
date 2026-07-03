// database/migrations/2026_01_01_000014_add_current_restaurant_id_to_users.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_restaurant_id')->nullable()->after('license_number')->constrained('restaurants')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_restaurant_id']);
            $table->dropColumn('current_restaurant_id');
        });
    }
};