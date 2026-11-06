<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A restaurant whose subscription payment failed must not look subscribed, and must be able to switch to commission. */
class RestaurantSubscriptionFailedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create();
        $owner->assignRole(Role::findOrCreate('restaurant_owner', 'web'));
        $this->restaurant = Restaurant::create([
            'owner_id' => $owner->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '700001',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'], 'is_open' => true,
            'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
        $this->plan = SubscriptionPlan::create(['audience' => 'restaurant', 'scope' => 'full_plan', 'name' => 'Pro', 'slug' => 'pro', 'billing_period' => 'monthly',
            'validity_days' => 30, 'price' => 999, 'currency' => 'INR', 'is_active' => true]);
        Sanctum::actingAs($owner);
    }

    private function sub(string $status): RestaurantSubscription
    {
        return RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $this->plan->id, 'scope' => 'full_plan',
            'status' => $status, 'gateway' => 'razorpay', 'gateway_subscription_id' => 'sub_' . uniqid()]);
    }

    public function test_an_unpaid_checkout_is_not_shown_as_the_restaurants_subscription(): void
    {
        $this->sub('pending_authorization');

        $data = $this->getJson('/api/restaurant/subscription/current')->assertOk()->json('data');

        $this->assertNull($data['subscription']);
        $this->assertSame('pending_authorization', $data['pending_subscription']['status']);
    }

    public function test_switching_to_commission_ends_an_unpaid_or_lapsed_subscription(): void
    {
        $unpaid = $this->sub('pending_authorization');
        $lapsed = $this->sub('billing_choice_required');
        $this->restaurant->forceFill(['billing_mode' => 'subscription', 'active_subscription_id' => $lapsed->id])->save();

        $this->postJson('/api/restaurant/subscription/switch-to-commission', ['consent' => true])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('commission', $this->restaurant->fresh()->billing_mode);
        $this->assertNull($this->restaurant->fresh()->active_subscription_id);
        $this->assertSame('cancelled', $unpaid->fresh()->status);
        $this->assertSame('cancelled', $lapsed->fresh()->status);
        $data = $this->getJson('/api/restaurant/subscription/current')->assertOk()->json('data');
        $this->assertNull($data['subscription']);
        $this->assertNull($data['pending_subscription']);
    }

    public function test_switching_works_with_no_subscription_at_all_and_requires_consent(): void
    {
        $this->postJson('/api/restaurant/subscription/switch-to-commission', [])->assertStatus(422);
        $this->postJson('/api/restaurant/subscription/switch-to-commission', ['consent' => true])->assertOk();
        $this->assertSame('commission', $this->restaurant->fresh()->billing_mode);
    }

    public function test_a_failed_checkout_does_not_block_trying_again(): void
    {
        $stale = $this->sub('pending_authorization');

        app(\App\Services\SubscriptionService::class)->discardUnpaidAttempts($this->restaurant);

        $this->assertSame('cancelled', $stale->fresh()->status);
    }
}
