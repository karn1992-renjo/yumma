<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Make state nullable first
            $table->string('state')->nullable()->change();
            $table->string('pincode')->nullable()->change();
        });
    }
    
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('state')->nullable(false)->change();
            $table->string('pincode')->nullable(false)->change();
        });
    }
};