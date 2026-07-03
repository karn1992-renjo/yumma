<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('address')->nullable()->after('license_number');
            $table->foreignId('delivery_area_id')->nullable()->after('address')->constrained('delivery_areas')->nullOnDelete();
            $table->decimal('latitude', 10, 8)->nullable()->after('delivery_area_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_area_id');
            $table->dropColumn(['address', 'latitude', 'longitude']);
        });
    }
};
