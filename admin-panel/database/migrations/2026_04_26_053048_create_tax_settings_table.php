// database/migrations/2026_01_01_000005_create_tax_settings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->string('type'); // gst, service_charge, packaging_charge, delivery_charge_tax
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tax_settings');
    }
};