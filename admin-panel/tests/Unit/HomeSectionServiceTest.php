<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Models\Cuisine;
use App\Models\HomeSection;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\HomeSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeSectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_runtime_order_with_built_in_and_dynamic_sections(): void
    {
        AppSetting::create(['key' => 'homepage_section_order', 'value' => json_encode(['restaurant_discovery', 'home_section:1', 'categories']), 'type' => 'string']);

        HomeSection::create([
            'title' => 'Featured Picks',
            'section_type' => 'restaurant_grid',
            'data_source' => 'auto',
            'configuration' => ['limit' => 6, 'restaurant_scope' => 'featured'],
            'display_order' => 1,
            'is_active' => true,
        ]);

        $resolved = app(HomeSectionService::class)->adminSections();

        $this->assertSame(
            ['restaurant_discovery', 'home_section:1', 'categories'],
            $resolved->pluck('token')->all()
        );
    }

    public function test_it_builds_public_sections_with_real_items_only(): void
    {
        Cuisine::create([
            'name' => 'Indian',
            'slug' => 'indian',
            'icon' => 'fas fa-pepper-hot',
            'is_active' => true,
            'display_order' => 1,
            'popular' => true,
        ]);

        Restaurant::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Spice Route',
            'slug' => 'spice-route',
            'address' => 'Main Street',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'phone' => '9999999999',
            'email' => 'spice@example.com',
            'cuisine' => ['Indian'],
            'is_open' => true,
            'is_pure_veg' => false,
            'min_order_amount' => 199,
            'delivery_fee' => 20,
            'delivery_time' => 30,
            'rating' => 4.7,
            'total_ratings' => 10,
            'is_featured' => true,
            'is_verified' => true,
        ]);

        HomeSection::create([
            'title' => 'Editor Picks',
            'section_type' => 'restaurant_grid',
            'data_source' => 'auto',
            'configuration' => ['limit' => 6, 'restaurant_scope' => 'featured'],
            'display_order' => 1,
            'is_active' => true,
        ]);

        $sections = app(HomeSectionService::class)->publicSections();

        $this->assertTrue($sections->pluck('token')->contains('categories'));
        $this->assertTrue($sections->pluck('token')->contains('home_section:1'));
        $this->assertSame('restaurant_grid', $sections->firstWhere('token', 'home_section:1')['type']);
    }
}
