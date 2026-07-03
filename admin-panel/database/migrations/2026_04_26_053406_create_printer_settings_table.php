// database/migrations/2026_01_01_000015_create_printer_settings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('printer_name');
            $table->string('printer_type'); // network, usb, bluetooth
            $table->string('ip_address')->nullable();
            $table->integer('port')->default(9100);
            $table->string('usb_path')->nullable();
            $table->integer('paper_size')->default(80); // mm
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('printer_settings');
    }
};