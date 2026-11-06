<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantStory;
use App\Models\User;
use App\Support\VideoOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Customers can like and share a story; opening it works by id or slug; uploads never fail on optimisation. */
class StoryLikeShareTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private RestaurantStory $story;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $this->restaurant = Restaurant::create([
            'owner_id' => $owner->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '700001',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'], 'is_open' => true,
            'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
        $this->story = RestaurantStory::create([
            'restaurant_id' => $this->restaurant->id, 'media_path' => 'restaurant-stories/a.jpg', 'media_type' => 'image',
            'posted_at' => now(), 'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_like_is_idempotent_and_unlike_reverses_it(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/stories/{$this->story->id}/like")->assertOk()->assertJson(['liked' => true, 'like_count' => 1]);
        $this->postJson("/api/stories/{$this->story->id}/like")->assertOk()->assertJson(['like_count' => 1]);

        $reel = $this->getJson('/api/restaurants/spice-hub/stories')->assertOk()->json('data.0');
        $this->assertTrue($reel['liked']);
        $this->assertSame(1, $reel['like_count']);

        $this->deleteJson("/api/stories/{$this->story->id}/like")->assertOk()->assertJson(['liked' => false, 'like_count' => 0]);
        $this->deleteJson("/api/stories/{$this->story->id}/like")->assertOk()->assertJson(['like_count' => 0]);
    }

    public function test_one_viewers_like_does_not_show_as_liked_for_another(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stories/{$this->story->id}/like")->assertOk();

        Sanctum::actingAs(User::factory()->create());
        $reel = $this->getJson('/api/restaurants/spice-hub/stories')->json('data.0');
        $this->assertFalse($reel['liked']);
        $this->assertSame(1, $reel['like_count']);
    }

    public function test_liking_needs_login_but_watching_does_not(): void
    {
        $this->postJson("/api/stories/{$this->story->id}/like")->assertUnauthorized();
        $this->getJson('/api/restaurants/spice-hub/stories')->assertOk()->assertJsonPath('data.0.liked', false);
    }

    public function test_share_is_counted_and_the_reel_opens_by_numeric_id_too(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/stories/{$this->story->id}/shared")->assertOk();
        $this->postJson("/api/stories/{$this->story->id}/shared")->assertOk();

        $byId = $this->getJson("/api/restaurants/{$this->restaurant->id}/stories")->assertOk()->json('data.0');
        $this->assertSame(2, $byId['share_count']);
        $this->assertSame('spice-hub', $byId['restaurant_slug']);
    }

    public function test_optimiser_hands_back_the_original_when_ffmpeg_is_unavailable_or_off(): void
    {
        \App\Models\AppSetting::setValue('ffmpeg_path', '/nonexistent/ffmpeg');
        \App\Models\AppSetting::setValue('story_video_optimize', '0');
        $file = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');

        $this->assertSame($file, VideoOptimizer::optimize($file));
    }
}
