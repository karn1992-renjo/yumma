<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('printer_settings', 'bluetooth_mac')) {
            Schema::table('printer_settings', function (Blueprint $table) {
                $table->string('bluetooth_mac')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('printer_settings', 'bluetooth_mac')) {
            Schema::table('printer_settings', function (Blueprint $table) {
                $table->dropColumn('bluetooth_mac');
            });
        }
    }
};
