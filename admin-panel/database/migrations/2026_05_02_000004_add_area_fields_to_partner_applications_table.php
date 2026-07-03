<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('address')->constrained('delivery_areas')->nullOnDelete();
            $table->decimal('latitude', 10, 8)->nullable()->after('area_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    public function down()
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
