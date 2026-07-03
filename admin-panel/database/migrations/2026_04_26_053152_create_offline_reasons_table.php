// database/migrations/2026_01_01_000008_create_offline_reasons_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('offline_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('reason');
            $table->json('sub_reasons')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_reasons');
    }
};