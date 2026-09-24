<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('printer_settings', 'printer_role')) {
                // kot | invoice | both — which documents this printer handles.
                $table->string('printer_role')->default('both')->after('printer_type');
            }
            if (! Schema::hasColumn('printer_settings', 'brand')) {
                // generic | epson | star | sunmi — drives ESC/POS quirks.
                $table->string('brand')->default('generic')->after('printer_role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('printer_settings', function (Blueprint $table) {
            foreach (['printer_role', 'brand'] as $column) {
                if (Schema::hasColumn('printer_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
