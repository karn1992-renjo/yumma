<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('driver_id')->constrained('delivery_areas')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('driver_gigs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
