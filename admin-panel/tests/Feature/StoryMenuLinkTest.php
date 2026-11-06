<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantStory;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Stories can carry a layout and a linked menu item with a button, when the plan allows it. */
class StoryMenuLinkTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private MenuItem $dish;

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
        $this->dish = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true, 'is_veg' => true]);
        Sanctum::actingAs($owner);
    }

    private function plan(bool $menuLink): void
    {
        $plan = SubscriptionPlan::create(['audience' => 'restaurant', 'scope' => 'stories_addon', 'name' => 'Stories', 'slug' => 'stories', 'billing_period' => 'monthly',
            'validity_days' => 30, 'price' => 299, 'currency' => 'INR', 'is_active' => true, 'includes_stories' => true, 'story_menu_link' => $menuLink]);
        RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $plan->id, 'scope' => 'stories_addon', 'status' => 'active',
            'payment_mode' => 'prepaid', 'auto_renew' => false, 'current_period_end' => now()->addDays(20)]);
    }

    private function upload(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/restaurant/stories', array_merge(['media_type' => 'image', 'media' => UploadedFile::fake()->image('s.jpg', 600, 900)], $extra));
    }

    public function test_a_plan_with_menu_link_attaches_the_dish_cta_and_layout_and_customers_see_them(): void
    {
        $this->plan(true);

        $res = $this->upload(['menu_item_id' => $this->dish->id, 'cta_label' => 'Try now', 'layout' => 'spotlight', 'caption' => 'Fresh today'])->assertCreated();
        $this->assertSame('spotlight', $res->json('data.layout'));
        $this->assertSame('Try now', $res->json('data.cta_label'));
        $this->assertEquals(['id' => $this->dish->id, 'name' => 'Paneer Tikka', 'price' => 220], array_intersect_key($res->json('data.menu_item'), array_flip(['id', 'name', 'price'])));

        $owner = $this->getJson('/api/restaurant/stories')->assertOk()->json();
        $this->assertTrue($owner['can_link_menu']);
        $this->assertSame('Paneer Tikka', $owner['data'][0]['menu_item']['name']);

        $customer = $this->getJson("/api/restaurants/{$this->restaurant->slug}/stories")->assertOk()->json('data');
        $this->assertSame(['spotlight', 'Try now', 'Paneer Tikka'], [$customer[0]['layout'], $customer[0]['cta_label'], $customer[0]['menu_item']['name']]);
    }

    public function test_default_cta_label_and_layout(): void
    {
        $this->plan(true);

        $res = $this->upload(['menu_item_id' => $this->dish->id])->assertCreated();

        $this->assertSame(['classic', 'Order now'], [$res->json('data.layout'), $res->json('data.cta_label')]);
    }

    public function test_a_plan_without_menu_link_refuses_the_dish_but_still_allows_a_layout(): void
    {
        $this->plan(false);

        $this->upload(['menu_item_id' => $this->dish->id])->assertStatus(403);
        $this->assertSame(0, RestaurantStory::count());

        $res = $this->upload(['layout' => 'poster'])->assertCreated();
        $this->assertSame('poster', $res->json('data.layout'));
        $this->assertNull($res->json('data.menu_item'));
        $this->assertNull($res->json('data.cta_label'));
        $this->assertFalse($this->getJson('/api/restaurant/stories')->json('can_link_menu'));
    }

    public function test_only_your_own_available_dishes_can_be_linked_and_a_sold_out_dish_hides_the_button(): void
    {
        $this->plan(true);
        $other = Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => 'Other', 'slug' => 'other', 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '1', 'latitude' => 1, 'longitude' => 1,
            'phone' => '9', 'email' => 'o@example.com', 'cuisine' => ['a'], 'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30,
            'rating' => 4, 'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true,
        ]);
        $theirs = MenuItem::create(['restaurant_id' => $other->id, 'name' => 'Dal', 'price' => 100, 'is_available' => true]);
        $this->upload(['menu_item_id' => $theirs->id])->assertStatus(422);
        $this->upload(['layout' => 'bogus'])->assertStatus(422);

        $this->upload(['menu_item_id' => $this->dish->id])->assertCreated();
        $this->dish->update(['is_available' => false]);                                  // sold out later

        $response = $this->getJson("/api/restaurants/{$this->restaurant->slug}/stories");
        $response->assertOk();
        $customer = $response->json('data');
        $this->assertNull($customer[0]['menu_item']);
    }

    public function test_the_plan_forms_save_the_menu_link_option(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.subscription-plans.stories.create'))->assertOk()->assertSee('Allow linking a menu item with a button');
        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Stories Plus', 'duration_days' => 30, 'price' => 399, 'story_menu_link' => 1, 'is_active' => 1]);
        $this->assertTrue((bool) SubscriptionPlan::firstWhere('name', 'Stories Plus')->story_menu_link);

        $this->post(route('admin.subscription-plans.stories.store'), ['name' => 'Stories Basic', 'duration_days' => 30, 'price' => 99]);
        $this->assertFalse((bool) SubscriptionPlan::firstWhere('name', 'Stories Basic')->story_menu_link);

        $this->post(route('admin.subscription-plans.store'), ['audience' => 'restaurant', 'scope' => 'full_plan', 'name' => 'Pro', 'billing_period' => 'monthly', 'price' => 999, 'includes_stories' => 1, 'story_menu_link' => 1]);
        $this->assertTrue((bool) SubscriptionPlan::firstWhere('name', 'Pro')->story_menu_link);
    }

    public function test_the_picker_lists_only_available_dishes_of_this_restaurant(): void
    {
        MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Sold Out Soup', 'price' => 90, 'is_available' => false]);

        $items = $this->getJson('/api/restaurant/stories/menu-items')->assertOk()->json('data');

        $this->assertSame(['Paneer Tikka'], array_column($items, 'name'));
        $this->assertEquals(220, $items[0]['price']);
    }
}
