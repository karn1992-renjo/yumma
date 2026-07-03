// database/migrations/2026_01_01_000006_create_dining_bookings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dining_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('booking_number')->unique();
            $table->date('booking_date');
            $table->time('booking_time');
            $table->integer('number_of_guests');
            $table->string('celebration_type')->nullable(); // birthday, anniversary, etc.
            $table->text('special_requests')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->decimal('booking_charge', 10, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('dining_bookings');
    }
};