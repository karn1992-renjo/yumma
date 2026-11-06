<?php

namespace Tests\Feature;

use App\Models\HomeSection;
use App\Models\Restaurant;
use App\Models\RestaurantStory;
use App\Models\User;
use App\Services\HomeSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Stories rail on the customer home appears by itself when restaurants have live stories. */
class HomeStoriesRailTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '700001',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'], 'is_open' => true,
            'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
    }

    private function story(Restaurant $r, int $expiresInHours = 5): RestaurantStory
    {
        return RestaurantStory::create(['restaurant_id' => $r->id, 'media_path' => 's.jpg', 'media_type' => 'image', 'posted_at' => now(), 'expires_at' => now()->addHours($expiresInHours)]);
    }

    private function storiesSection(): ?array
    {
        return app(HomeSectionService::class)->publicSections()->firstWhere('type', 'restaurant_stories');
    }

    public function test_the_stories_rail_shows_without_any_admin_setup_when_a_story_is_live(): void
    {
        $this->assertNull($this->storiesSection());                          // nothing to show yet

        $r = $this->restaurant();
        $this->story($r);

        $section = $this->storiesSection();
        $this->assertNotNull($section);
        $this->assertSame('restaurant_stories', $section['token']);
        $this->assertSame('Stories', $section['title']);
        $this->assertCount(1, $section['items']);
        $this->assertSame($r->id, $section['items'][0]['restaurant_id']);
        $this->assertSame(1, $section['items'][0]['story_count']);
    }

    public function test_an_expired_story_does_not_show_the_rail(): void
    {
        $this->story($this->restaurant(), -1);

        $this->assertNull($this->storiesSection());
    }

    public function test_an_admin_created_stories_section_replaces_the_automatic_one(): void
    {
        $r = $this->restaurant();
        $this->story($r);
        $custom = HomeSection::create(['title' => 'Chef stories', 'section_type' => 'restaurant_stories', 'data_source' => 'auto', 'display_order' => 1, 'is_active' => true]);

        $all = app(HomeSectionService::class)->publicSections()->where('type', 'restaurant_stories');

        $this->assertCount(1, $all);                                         // never two rails
        $this->assertSame('home_section:' . $custom->id, $all->first()['token']);
    }
}
