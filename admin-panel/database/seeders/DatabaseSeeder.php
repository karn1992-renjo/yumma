<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Banner;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        
        // Create default app settings
        AppSetting::updateOrCreate(['key' => 'app_name'], ['value' => 'Food Delivery', 'type' => 'string']);
        AppSetting::updateOrCreate(['key' => 'commission_rate'], ['value' => '15', 'type' => 'number']);
        AppSetting::updateOrCreate(['key' => 'delivery_base_fee'], ['value' => '40', 'type' => 'number']);
        AppSetting::updateOrCreate(['key' => 'free_delivery_min_amount'], ['value' => '200', 'type' => 'number']);
        AppSetting::updateOrCreate(['key' => 'tax_rate'], ['value' => '5', 'type' => 'number']);
        
        // Create sample banners
        Banner::updateOrCreate(['id' => 1], [
            'title' => 'Welcome to Food Delivery',
            'description' => 'Get 20% off on your first order',
            'image' => 'banners/welcome-banner.jpg',
            'link' => '/offers',
            'display_order' => 1,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonths(1),
        ]);
        
        Banner::updateOrCreate(['id' => 2], [
            'title' => 'Free Delivery',
            'description' => 'On orders above ₹200',
            'image' => 'banners/free-delivery.jpg',
            'link' => '/restaurants',
            'display_order' => 2,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonths(1),
        ]);
        
        $this->command->info('Database seeded successfully!');
    }
}
