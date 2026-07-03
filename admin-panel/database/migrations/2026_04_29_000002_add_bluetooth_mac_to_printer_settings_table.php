<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('printer_settings', 'bluetooth_mac')) {
                $table->string('bluetooth_mac')->nullable()->after('usb_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            if (Schema::hasColumn('printer_settings', 'bluetooth_mac')) {
                $table->dropColumn('bluetooth_mac');
            }
        });
    }
};
