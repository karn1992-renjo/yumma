<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\CustomerSubscription;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** WhatsApp for restaurants and drivers: their notifications and their own assistant menus. */
class WhatsAppPartnerRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]])]);
    }

    private function owner(string $phone = '9222222222'): User
    {
        $owner = User::factory()->create(['name' => 'Ravi Owner', 'phone' => $phone]);
        $owner->assignRole(Role::findOrCreate('restaurant_owner', 'web'));

        return $owner;
    }

    private function restaurant(User $owner, array $override = []): Restaurant
    {
        return Restaurant::create($override + [
            'owner_id' => $owner->id, 'name' => 'Spice Hub', 'slug' => 'sh-' . uniqid(), 'address' => 'Main Street', 'city' => 'Kolkata', 'state' => 'WB',
            'pincode' => '700001', 'latitude' => 22.57, 'longitude' => 88.36, 'phone' => '9000000001', 'email' => uniqid() . '@e.com', 'cuisine' => ['Indian'],
            'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
    }

    private function order(Restaurant $restaurant, array $override = []): Order
    {
        $customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);

        return Order::create($override + [
            'order_number' => 'ORD' . random_int(10000000, 99999999), 'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery',
            'items' => [['name' => 'Paneer', 'quantity' => 2]], 'subtotal' => 250, 'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 250,
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'customer_name' => 'Asha Verma', 'customer_phone' => '9876543210',
            'customer_address' => ['address' => '12 Park Street'], 'delivery_address' => '12 Park Street', 'delivery_lat' => 22.57, 'delivery_lng' => 88.36,
        ]);
    }

    private function events(): array
    {
        return WhatsAppMessage::where('direction', 'out')->orderBy('id')->get(['event', 'phone'])->map(fn ($m) => $m->event . '@' . substr($m->phone, -10))->all();
    }

    // ---- Notifications ---------------------------------------------------------------------------------

    public function test_restaurant_and_driver_are_notified_across_the_order_lifecycle(): void
    {
        $owner = $this->owner('9222222222');
        $restaurant = $this->restaurant($owner);
        $driver = User::factory()->create(['name' => 'Imran', 'phone' => '9111111111']);

        $order = $this->order($restaurant);                       // COD: placed straight away
        $events = $this->events();
        $this->assertContains('restaurant_new_order@9000000001', $events);   // restaurant's own number
        $this->assertContains('restaurant_new_order@9222222222', $events);   // and the owner's

        $order->update(['driver_id' => $driver->id]);
        $events = $this->events();
        $this->assertContains('restaurant_rider_assigned@9000000001', $events);
        $this->assertContains('driver_new_request@9111111111', $events);

        $order->update(['status' => 'cancelled', 'cancellation_reason' => 'Customer changed mind', 'cancelled_by_role' => 'customer']);
        $events = $this->events();
        $this->assertContains('restaurant_order_cancelled@9222222222', $events);
        $this->assertContains('driver_order_cancelled@9111111111', $events);
        $this->assertStringContainsString('Customer changed mind', WhatsAppMessage::where('event', 'driver_order_cancelled')->value('body'));
    }

    public function test_payouts_wallet_credits_failed_payments_and_approvals(): void
    {
        $owner = $this->owner('9222222222');
        $restaurant = $this->restaurant($owner, ['is_verified' => false]);
        $driver = User::factory()->create(['phone' => '9111111111']);

        $restaurant->update(['is_verified' => true]);
        $this->assertContains('restaurant_approved@9000000001', $this->events());

        $p1 = Payout::create(['restaurant_id' => $restaurant->id, 'amount' => 1200, 'net_amount' => 1200, 'status' => 'pending', 'period_start' => now()->subWeek(), 'period_end' => now()]);
        $p1->update(['status' => 'completed']);
        $p2 = Payout::create(['driver_id' => $driver->id, 'amount' => 800, 'net_amount' => 800, 'status' => 'pending', 'period_start' => now()->subWeek(), 'period_end' => now()]);
        $p2->update(['status' => 'completed']);
        $events = $this->events();
        $this->assertContains('payout_paid@9222222222', $events);
        $this->assertContains('payout_paid@9111111111', $events);

        $customer = User::factory()->create(['phone' => '9876543210']);
        $wallet = Wallet::create(['user_id' => $customer->id, 'balance' => 500, 'locked_balance' => 0, 'currency' => 'INR', 'is_active' => true]);
        WalletTransaction::create(['wallet_id' => $wallet->id, 'user_id' => $customer->id, 'type' => 'credit', 'amount' => 500, 'balance_after' => 500, 'description' => 'Cashback']);
        WalletTransaction::create(['wallet_id' => $wallet->id, 'user_id' => $customer->id, 'type' => 'credit', 'amount' => 90, 'balance_after' => 590, 'description' => 'Refund for order']);
        $this->assertSame(1, WhatsAppMessage::where('event', 'wallet_credit')->count());   // refund credits are not announced twice

        $order = $this->order($restaurant, ['payment_method' => 'razorpay']);
        $attempt = PaymentAttempt::create(['order_id' => $order->id, 'gateway' => 'razorpay', 'source' => 'customer', 'status' => 'pending', 'amount' => 250, 'currency' => 'INR']);
        $attempt->update(['status' => PaymentAttempt::STATUS_FAILED]);
        $this->assertSame(1, WhatsAppMessage::where('event', 'payment_failed')->count());
    }

    public function test_gold_expiry_reminder_goes_out_once(): void
    {
        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $plan = SubscriptionPlan::create(['audience' => 'customer', 'scope' => 'full_plan', 'name' => 'Gold', 'slug' => 'g-' . uniqid(), 'billing_period' => 'monthly',
            'validity_days' => 30, 'price' => 99, 'currency' => 'INR', 'is_active' => true]);
        CustomerSubscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
            'current_period_start' => now()->subDays(28), 'current_period_end' => now()->addDays(2), 'auto_renew' => false]);

        $notifier = app(WhatsAppNotifier::class);
        $this->assertSame(1, $notifier->sendReminders());
        $notifier->sendReminders();                                // running daily must not repeat it
        $this->assertSame(1, WhatsAppMessage::where('event', 'gold_expiring')->count());
    }

    // ---- Assistant ---------------------------------------------------------------------------------------

    private function say(string $from, string $text): void
    {
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'X']]],
            'messages' => [['id' => 'wamid.' . uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    private function reply(): string
    {
        return (string) WhatsAppMessage::where('direction', 'out')->where('event', 'bot')->latest('id')->value('body');
    }

    public function test_restaurant_owner_gets_a_business_menu_summary_and_can_close_and_open(): void
    {
        $owner = $this->owner('9222222222');
        $restaurant = $this->restaurant($owner);
        $this->order($restaurant);                                // pending, 250
        $this->order($restaurant, ['status' => 'delivered']);

        $this->say('919222222222', 'hi');
        $this->assertStringContainsString('restaurant assistant', $this->reply());

        $this->say('919222222222', '1');
        $this->assertStringContainsString('Orders needing action', $this->reply());

        $this->say('919222222222', '2');
        $this->assertStringContainsString('Orders: 2', $this->reply());
        $this->assertStringContainsString('Delivered: 1', $this->reply());

        $this->say('919222222222', 'close');
        $this->assertFalse($restaurant->fresh()->is_open);
        $this->say('919222222222', 'open');
        $this->assertTrue($restaurant->fresh()->is_open);

        // a stranger can never look at this restaurant's orders
        $other = User::factory()->create(['phone' => '9333333333']);
        $this->say('919333333333', 'hi');
        $this->assertStringContainsString('Track my order', $this->reply());

        // an owner can still use the customer menu
        $this->say('919222222222', 'customer');
        $this->assertStringContainsString('Track my order', $this->reply());
        $this->assertStringContainsString('PARTNER', $this->reply());
        $this->say('919222222222', 'partner');
        $this->assertStringContainsString('restaurant assistant', $this->reply());
    }

    public function test_driver_sees_the_current_delivery_with_cash_to_collect_but_never_the_otp(): void
    {
        $restaurant = $this->restaurant($this->owner('9222222222'));
        $driver = User::factory()->create(['name' => 'Imran Khan', 'phone' => '9111111111']);
        $driver->assignRole(Role::findOrCreate('delivery_partner', 'web'));
        $order = $this->order($restaurant, ['driver_id' => $driver->id, 'status' => 'picked_up', 'delivery_otp' => '7788']);
        Order::withoutEvents(fn () => $this->order($restaurant, ['driver_id' => $driver->id, 'status' => 'delivered', 'delivered_at' => now(), 'driver_earning' => 45]));

        $this->say('919111111111', 'menu');
        $this->assertStringContainsString('delivery assistant', $this->reply());

        $this->say('919111111111', '1');
        $reply = $this->reply();
        $this->assertStringContainsString($order->order_number, $reply);
        $this->assertStringContainsString('Spice Hub', $reply);
        $this->assertStringContainsString('12 Park Street', $reply);
        $this->assertStringContainsString('Collect cash', $reply);
        $this->assertStringNotContainsString('7788', $reply);

        $this->say('919111111111', '2');
        $this->assertStringContainsString('Deliveries: 1', $this->reply());
        $this->assertStringContainsString('45', $this->reply());
    }
}
