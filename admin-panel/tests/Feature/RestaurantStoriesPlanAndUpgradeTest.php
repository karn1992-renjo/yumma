<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Restaurant;
use App\Models\RestaurantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StoriesAccessService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Stories-only pay-as-you-go plan, plan upgrade, and trial access once the mandate is authorized. */
class RestaurantStoriesPlanAndUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('razorpay_key', 'rzp_test_key');
        AppSetting::setValue('razorpay_secret', 'secret');
        AppSetting::setValue('payment_razorpay_enabled', '1');
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

    private function plan(string $name, float $price, string $scope = 'stories_addon', array $extra = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'audience' => 'restaurant', 'scope' => $scope, 'name' => $name, 'slug' => str($name)->slug(), 'billing_period' => 'monthly', 'validity_days' => 30,
            'price' => $price, 'currency' => 'INR', 'is_active' => true,
        ], $extra));
    }

    private function fakeRazorpay(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_S1', 'amount' => 29900, 'currency' => 'INR']),
            'api.razorpay.com/v1/payments/pay_1' => Http::response(['id' => 'pay_1', 'order_id' => 'order_S1', 'amount' => 29900, 'status' => 'captured']),
        ]);
    }

    private function verify(int $subId, string $signature = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/restaurant/subscription/verify', [
            'subscription_id' => $subId, 'payment_method' => 'razorpay', 'payment_id' => 'pay_1', 'razorpay_order_id' => 'order_S1',
            'razorpay_signature' => $signature ?? hash_hmac('sha256', 'order_S1|pay_1', 'secret'),
        ]);
    }

    public function test_a_commission_restaurant_buys_a_stories_plan_one_payment_at_a_time(): void
    {
        $plan = $this->plan('Stories Monthly', 299);
        $this->fakeRazorpay();
        $this->assertFalse(app(StoriesAccessService::class)->canPost($this->restaurant));

        $res = $this->postJson('/api/restaurant/subscription/subscribe', ['plan_id' => $plan->id, 'payment_method' => 'razorpay'])->assertOk();
        $this->assertSame('prepaid', $res->json('data.payment_mode'));
        $this->assertSame('order_S1', $res->json('data.checkout.order_id'));
        $subId = $res->json('data.subscription.id');
        $this->assertFalse(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));      // not paid yet

        $this->verify($subId)->assertOk()->assertJsonPath('data.subscription.status', 'active');

        $sub = RestaurantSubscription::find($subId);
        $this->assertSame('prepaid', $sub->payment_mode);
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertTrue(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));
        $this->assertSame('commission', $this->restaurant->fresh()->billing_mode);                         // stays on commission

        $this->travel(31)->days();                                                                          // the paid period is over
        $this->assertFalse(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));
        $current = $this->getJson('/api/restaurant/subscription/current')->assertOk()->json('data');
        $this->assertNull($current['stories_addon_subscription']);
        $this->assertFalse($current['can_post_stories']);
    }

    public function test_a_forged_payment_does_not_unlock_stories(): void
    {
        $plan = $this->plan('Stories Monthly', 299);
        $this->fakeRazorpay();
        $subId = $this->postJson('/api/restaurant/subscription/subscribe', ['plan_id' => $plan->id])->json('data.subscription.id');

        $this->verify($subId, 'forged')->assertStatus(422);

        $this->assertSame('pending_authorization', RestaurantSubscription::find($subId)->status);
        $this->assertFalse(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));
    }

    public function test_upgrading_a_stories_plan_replaces_it_only_after_the_new_one_is_paid(): void
    {
        $small = $this->plan('Stories 7 days', 99, 'stories_addon', ['validity_days' => 7]);
        $big = $this->plan('Stories 30 days', 299);
        $old = RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $small->id, 'scope' => 'stories_addon', 'status' => 'active',
            'payment_mode' => 'prepaid', 'auto_renew' => false, 'current_period_start' => now(), 'current_period_end' => now()->addDays(5)]);
        $this->fakeRazorpay();

        $current = $this->getJson('/api/restaurant/subscription/current')->json('data');
        $this->assertSame([$big->id], array_column($current['stories_upgrade_plans'], 'id'));

        $this->postJson('/api/restaurant/subscription/upgrade', ['plan_id' => $small->id])->assertStatus(422);        // same plan
        $res = $this->postJson('/api/restaurant/subscription/upgrade', ['plan_id' => $big->id])->assertOk();
        $this->assertSame('active', $old->fresh()->status);                                                           // still running until paid
        $this->assertTrue(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));

        $this->verify($res->json('data.subscription.id'))->assertOk();

        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('active', RestaurantSubscription::find($res->json('data.subscription.id'))->status);
    }

    public function test_a_cheaper_plan_is_not_an_upgrade_and_nothing_to_upgrade_is_refused(): void
    {
        $small = $this->plan('Stories 7 days', 99, 'stories_addon', ['validity_days' => 7]);
        $big = $this->plan('Stories 30 days', 299);
        $this->postJson('/api/restaurant/subscription/upgrade', ['plan_id' => $big->id])->assertStatus(422);          // no current plan
        RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $big->id, 'scope' => 'stories_addon', 'status' => 'active',
            'payment_mode' => 'prepaid', 'auto_renew' => false, 'current_period_end' => now()->addDays(20)]);
        $this->postJson('/api/restaurant/subscription/upgrade', ['plan_id' => $small->id])->assertStatus(422)->assertJsonPath('message', 'Choose a plan with a higher price to upgrade.');
    }

    public function test_a_plan_with_a_trial_gives_access_as_soon_as_the_mandate_is_authorized(): void
    {
        $plan = $this->plan('Pro', 999, 'full_plan', ['trial_days' => 7, 'includes_stories' => true]);
        $sub = RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $plan->id, 'scope' => 'full_plan', 'status' => 'pending_authorization',
            'gateway' => 'razorpay', 'gateway_subscription_id' => 'sub_X', 'trial_ends_at' => now()->addDays(7)]);
        $this->assertFalse(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));

        app(SubscriptionService::class)->markAuthorized($sub);

        $this->assertSame('trialing', $sub->fresh()->status);
        $this->assertSame('subscription', $this->restaurant->fresh()->billing_mode);
        $this->assertSame($sub->id, $this->restaurant->fresh()->active_subscription_id);
        $this->assertTrue(app(StoriesAccessService::class)->canPost($this->restaurant->fresh()));
    }

    public function test_upgrading_a_recurring_plan_ends_the_old_one_when_the_new_one_activates(): void
    {
        $basic = $this->plan('Basic', 500, 'full_plan');
        $pro = $this->plan('Pro', 900, 'full_plan');
        $old = RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $basic->id, 'scope' => 'full_plan', 'status' => 'active',
            'current_period_end' => now()->addDays(10)]);
        $new = RestaurantSubscription::create(['restaurant_id' => $this->restaurant->id, 'subscription_plan_id' => $pro->id, 'scope' => 'full_plan', 'status' => 'pending_authorization',
            'replaces_subscription_id' => $old->id]);

        app(SubscriptionService::class)->activateFromCharge($new, ['amount' => 900, 'currency' => 'INR', 'payment_id' => 'pay_9']);

        $this->assertSame('active', $new->fresh()->status);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame($new->id, $this->restaurant->fresh()->active_subscription_id);
    }
}
