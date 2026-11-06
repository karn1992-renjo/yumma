<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantStory;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StoriesAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Stories plan limits: stories per day, and how long each story stays live. */
class StoriesPlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $owner = User::factory()->create();
        $owner->assignRole(Role::findOrCreate('restaurant_owner', 'web'));
        $this->restaurant = Restaurant::create([
            'owner_id' => $owner->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '700001',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'], 'is_open' => true,
            'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
        $this->restaurant->forceFill(['billing_mode' => 'commission'])->save();
        Sanctum::actingAs($owner);
    }

    private function subscribe(array $planExtra): SubscriptionPlan
    {
        $plan = SubscriptionPlan::create(array_merge(['audience' => 'restaurant', 'scope' => 'stories_addon', 'name' => 'Stories', 'slug' => 'stories', 'billing_period' => 'monthly',
            'validity_days' => 30, 'price' => 299, 'currency' => 'INR', 'is_active' => true, 'includes_stories' => true], $planExtra));
        RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $plan->id, 'scope' => 'stories_addon', 'status' => 'active',
            'payment_mode' => 'prepaid', 'auto_renew' => false, 'current_period_end' => now()->addDays(20)]);

        return $plan;
    }

    private function postStory(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/restaurant/stories', ['media_type' => 'image', 'media' => UploadedFile::fake()->image('s.jpg', 600, 900)]);
    }

    public function test_the_daily_limit_stops_extra_stories_and_resets_the_next_day(): void
    {
        $this->subscribe(['stories_per_day' => 2]);

        $this->postStory()->assertCreated();
        $this->postStory()->assertCreated();
        $res = $this->postStory()->assertStatus(429);
        $this->assertStringContainsString("today's limit of 2 stories", $res->json('message'));

        $list = $this->getJson('/api/restaurant/stories')->assertOk()->json();
        $this->assertSame(['per_day' => 2, 'hours' => 24, 'posted_today' => 2, 'left_today' => 0], $list['limits']);

        $this->travel(1)->day();
        $this->postStory()->assertCreated();                                    // a new day
    }

    public function test_each_story_lives_for_the_plans_hours(): void
    {
        $this->subscribe(['story_duration_hours' => 72]);

        $this->postStory()->assertCreated();

        $story = RestaurantStory::firstOrFail();
        $this->assertEqualsWithDelta(72 * 3600, $story->posted_at->diffInSeconds($story->expires_at), 5);
    }

    public function test_no_limits_set_means_unlimited_and_24_hours(): void
    {
        $this->subscribe([]);

        foreach (range(1, 4) as $i) {
            $this->postStory()->assertCreated();
        }
        $limits = app(StoriesAccessService::class)->limits($this->restaurant->fresh());
        $this->assertNull($limits['per_day']);
        $this->assertNull($limits['left_today']);
        $this->assertSame(24, $limits['hours']);
    }

    public function test_the_stories_plan_form_saves_the_limits_and_the_plans_api_shows_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.subscription-plans.stories.create'))->assertOk()->assertSee('Stories allowed per day')->assertSee('Each story stays live for');
        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Stories Pro', 'duration_days' => 30, 'price' => 499, 'stories_per_day' => 5, 'story_duration_hours' => 48, 'is_active' => 1]);
        $plan = SubscriptionPlan::firstWhere('name', 'Stories Pro');
        $this->assertSame([5, 48], [$plan->stories_per_day, $plan->story_duration_hours]);

        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Bad', 'duration_days' => 30, 'price' => 10, 'stories_per_day' => 0])->assertSessionHasErrors('stories_per_day');

        Sanctum::actingAs($this->restaurant->owner);
        $api = $this->getJson('/api/restaurant/subscription/plans?scope=stories_addon')->assertOk()->json('data');
        $this->assertSame([5, 48], [$api[0]['stories_per_day'], $api[0]['story_duration_hours']]);
    }
}
