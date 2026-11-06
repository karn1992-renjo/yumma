<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantAdCampaign;
use App\Models\RestaurantAdWallet;
use App\Models\User;
use App\Services\SponsoredListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Active ad campaigns show on every list that shows restaurant cards, not only nearby and search. */
class SponsoredEverywhereTest extends TestCase
{
    use RefreshDatabase;

    private function restaurant(string $name, float $rating): Restaurant
    {
        return Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => $name, 'slug' => str($name)->slug(), 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '700001',
            'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001', 'email' => str($name)->slug() . '@example.com', 'cuisine' => ['Indian'], 'is_open' => true,
            'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => $rating, 'total_ratings' => 5,
            'is_featured' => false, 'is_verified' => true, 'restaurant_type' => 'both',
        ]);
    }

    private function advertise(Restaurant $r): RestaurantAdCampaign
    {
        RestaurantAdWallet::create(['restaurant_id' => $r->id, 'balance' => 500, 'currency' => 'INR', 'is_active' => true]);

        return RestaurantAdCampaign::create(['restaurant_id' => $r->id, 'name' => 'Boost', 'status' => 'active', 'max_cpc' => 5, 'starts_at' => today()->subDay(),
            'targeting' => ['automatic_placement' => true]]);
    }

    public function test_the_service_marks_the_advertiser_and_pins_it_first(): void
    {
        $a = $this->restaurant('Alpha', 4.8);
        $b = $this->restaurant('Beta', 4.0);
        $campaign = $this->advertise($b);

        $out = app(SponsoredListingService::class)->stampCards([['id' => $a->id, 'name' => 'Alpha'], ['id' => $b->id, 'name' => 'Beta']], 'favorites');

        $this->assertSame('Beta', $out[0]['name']);
        $this->assertTrue($out[0]['is_sponsored']);
        $this->assertSame($campaign->id, $out[0]['ad_campaign_id']);
        $this->assertArrayNotHasKey('is_sponsored', $out[1]);
        $this->assertDatabaseHas('ad_impressions', ['restaurant_ad_campaign_id' => $campaign->id, 'restaurant_id' => $b->id, 'surface' => 'favorites']);
    }

    public function test_saved_restaurants_dining_and_search_lists_show_the_ad(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::findOrCreate('customer', 'web'));
        $a = $this->restaurant('Alpha', 4.8);
        $b = $this->restaurant('Beta', 4.0);
        $this->advertise($b);
        $customer->favoriteRestaurants()->attach([$a->id, $b->id]);
        Sanctum::actingAs($customer);

        $saved = $this->getJson('/api/favorites/restaurants')->assertOk()->json('data');
        $this->assertSame('Beta', $saved[0]['name']);
        $this->assertTrue($saved[0]['is_sponsored']);

        $dining = $this->getJson('/api/dining/restaurants?lat=22.5726&lng=88.3639')->assertOk()->json('data');
        $this->assertTrue(collect($dining)->firstWhere('name', 'Beta')['is_sponsored']);
        $this->assertFalse((bool) (collect($dining)->firstWhere('name', 'Alpha')['is_sponsored'] ?? false));
    }

    public function test_an_unfunded_or_paused_campaign_is_never_shown(): void
    {
        $b = $this->restaurant('Beta', 4.0);
        $c = $this->advertise($b);
        $c->update(['status' => 'paused']);

        $out = app(SponsoredListingService::class)->stampCards([['id' => $b->id, 'name' => 'Beta']], 'favorites');

        $this->assertArrayNotHasKey('is_sponsored', $out[0]);
    }
}
