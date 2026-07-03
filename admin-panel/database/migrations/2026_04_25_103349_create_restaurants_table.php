<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 10);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('phone', 20);
            $table->string('email');
            $table->json('cuisine')->nullable();
            $table->boolean('is_open')->default(true);
            $table->boolean('is_pure_veg')->default(false);
            $table->integer('min_order_amount')->default(0);
            $table->integer('delivery_fee')->default(0);
            $table->integer('delivery_time')->default(30);
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_ratings')->default(0);
            $table->string('banner_image')->nullable();
            $table->string('logo_image')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('ad_expiry')->nullable();
            $table->timestamps();
            
            $table->index(['city', 'is_open']);
            $table->index('slug');
        });
        
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('image')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 5);
            $table->decimal('discounted_price', 12, 5)->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_veg')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_recommended')->default(false);
            $table->integer('preparation_time')->default(15);
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_orders')->default(0);
            $table->json('tags')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'is_available']);
        });
        
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('restaurant_id')->constrained();
            $table->foreignId('driver_id')->nullable()->constrained('users');
            $table->json('items');
            $table->integer('subtotal');
            $table->integer('delivery_fee');
            $table->integer('tax');
            $table->integer('discount')->default(0);
            $table->integer('total');
            $table->string('payment_method');
            $table->enum('payment_status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('payment_id')->nullable();
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'ready_for_pickup',
                'picked_up', 'on_the_way', 'delivered', 'cancelled', 'refunded'
            ])->default('pending');
            $table->json('customer_address');
            $table->string('customer_phone');
            $table->string('customer_name');
            $table->text('delivery_address');
            $table->decimal('delivery_lat', 10, 8)->nullable();
            $table->decimal('delivery_lng', 11, 8)->nullable();
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'status']);
            $table->index('order_number');
            $table->index('customer_id');
        });
        
        Schema::create('driver_gigs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->date('date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', ['available', 'booked', 'completed', 'cancelled'])->default('available');
            $table->integer('earnings')->default(0);
            $table->integer('orders_count')->default(0);
            $table->timestamps();
            
            $table->index(['date', 'status']);
        });
        
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained();
            $table->foreignId('driver_id')->nullable()->constrained('users');
            $table->integer('amount');
            $table->enum('status', ['pending', 'processed', 'completed'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image');
            $table->string('link')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
        
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('driver_gigs');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('restaurants');
    }
};
