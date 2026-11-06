<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Cancel / accept / reject / go online-offline from WhatsApp, through the app's own rules. */
class WhatsAppActionsTest extends TestCase
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
        foreach (['customer', 'restaurant_owner', 'delivery_partner'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

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

    private function restaurant(User $owner): Restaurant
    {
        return Restaurant::create([
            'owner_id' => $owner->id, 'name' => 'Spice Hub', 'slug' => 'sh-' . uniqid(), 'address' => 'Main Street', 'city' => 'Kolkata', 'state' => 'WB',
            'pincode' => '700001', 'latitude' => 22.57, 'longitude' => 88.36, 'phone' => '9000000001', 'email' => uniqid() . '@e.com', 'cuisine' => ['Indian'],
            'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1,
            'is_featured' => false, 'is_verified' => true,
        ]);
    }

    private function order(User $customer, Restaurant $restaurant, array $override = []): Order
    {
        return Order::create($override + [
            'order_number' => 'ORD' . random_int(10000000, 99999999), 'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery',
            'items' => [['name' => 'Paneer', 'quantity' => 1]], 'subtotal' => 250, 'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 250,
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'customer_name' => $customer->name, 'customer_phone' => $customer->phone,
            'customer_address' => ['address' => '12 Park Street'], 'delivery_address' => '12 Park Street', 'delivery_lat' => 22.57, 'delivery_lng' => 88.36,
        ]);
    }

    public function test_customer_cancels_their_own_order_after_confirming(): void
    {
        $customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $customer->assignRole('customer');
        $order = $this->order($customer, $this->restaurant(User::factory()->create(['phone' => '9222222222'])));

        $this->say('919876543210', 'cancel');
        $this->assertStringContainsString("Cancel order #{$order->order_number}", $this->reply());
        $this->assertStringContainsString('YES', $this->reply());
        $this->assertSame('pending', $order->fresh()->status);                 // nothing happens before YES

        $this->say('919876543210', 'no');
        $this->assertSame('pending', $order->fresh()->status);

        $this->say('919876543210', 'cancel ' . $order->order_number);
        $this->say('919876543210', 'yes');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('customer', $order->fresh()->cancelled_by_role);
        $this->assertStringContainsString('cancelled', strtolower($this->reply()));
    }

    public function test_customer_cannot_cancel_someone_elses_or_a_delivered_order(): void
    {
        $asha = User::factory()->create(['phone' => '9876543210']);
        $other = User::factory()->create(['phone' => '9000000099']);
        $restaurant = $this->restaurant(User::factory()->create(['phone' => '9222222222']));
        $theirs = $this->order($other, $restaurant, ['customer_phone' => '9000000099']);
        $delivered = $this->order($asha, $restaurant, ['status' => 'delivered']);

        $this->say('919876543210', 'cancel ' . $theirs->order_number);
        $this->assertStringContainsString("couldn't find order", $this->reply());
        $this->assertSame('pending', $theirs->fresh()->status);

        $this->say('919876543210', 'cancel');
        $this->assertStringContainsString('no order that can be cancelled', $this->reply());
        $this->assertSame('delivered', $delivered->fresh()->status);
    }

    public function test_restaurant_accepts_and_rejects_only_its_own_orders(): void
    {
        // The sync test queue ignores the 2-minute delay of the auto-preparing job and re-queues it forever.
        \Illuminate\Support\Facades\Queue::fake();
        $owner = User::factory()->create(['name' => 'Ravi', 'phone' => '9222222222']);
        $owner->assignRole('restaurant_owner');
        $restaurant = $this->restaurant($owner);
        $customer = User::factory()->create(['phone' => '9876543210']);

        $a = $this->order($customer, $restaurant);
        $b = $this->order($customer, $restaurant);

        $this->say('919222222222', 'accept');                                  // two pending: must be specific
        $this->assertStringContainsString('include the order number', $this->reply());
        $this->assertSame('pending', $a->fresh()->status);

        $this->say('919222222222', "ACCEPT {$a->order_number} 25");
        $this->assertContains($a->fresh()->status, ['confirmed', 'preparing'], 'bot said: ' . $this->reply());
        $this->assertStringContainsString('accepted', $this->reply());

        $this->say('919222222222', "reject {$b->order_number}");
        $this->assertStringContainsString('add a reason', $this->reply());
        $this->assertSame('pending', $b->fresh()->status);

        $this->say('919222222222', "reject {$b->order_number} Out of stock");
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame('restaurant', $b->fresh()->cancelled_by_role);
        $this->assertSame('Out of stock', $b->fresh()->cancellation_reason);

        // another restaurant's order is untouchable
        $strangerRestaurant = $this->restaurant(User::factory()->create(['phone' => '9444444444']));
        $foreign = $this->order($customer, $strangerRestaurant);
        $this->say('919222222222', "accept {$foreign->order_number}");
        $this->assertStringContainsString("couldn't find order", $this->reply());
        $this->assertSame('pending', $foreign->fresh()->status);
    }

    public function test_driver_can_see_cash_in_hand_and_go_offline_and_online(): void
    {
        $driver = User::factory()->create(['name' => 'Imran Khan', 'phone' => '9111111111']);
        $driver->assignRole('delivery_partner');
        Cache::put("driver_status_{$driver->id}", ['is_online' => true, 'online_started_at' => now()->toIso8601String()], now()->addDay());

        $this->say('919111111111', 'menu');
        $this->assertStringContainsString('Cash in hand', $this->reply());

        $this->say('919111111111', '4');
        $this->assertStringContainsString('Cash in hand', $this->reply());
        $this->assertStringContainsString('Limit', $this->reply());

        $this->say('919111111111', '5');
        $this->assertStringContainsString('ONLINE', $this->reply());

        $this->say('919111111111', 'offline');
        $this->assertFalse(Cache::get("driver_status_{$driver->id}")['is_online']);
        $this->assertStringContainsString('OFFLINE', $this->reply());
        // told by the assistant itself, not by a second notification template
        $this->assertSame(0, WhatsAppMessage::where('event', 'driver_status_changed')->count());

        // The app's own rule applies here too: no booked gig, no going online.
        $this->say('919111111111', 'online');
        $this->assertFalse(Cache::get("driver_status_{$driver->id}")['is_online']);
        $this->assertStringContainsString('Book an active gig', $this->reply());

        // With gigs not required (or a salaried driver) the same command works.
        AppSetting::setValue('gig_required_for_online', '0');
        $this->say('919111111111', 'online');
        $this->assertTrue(Cache::get("driver_status_{$driver->id}")['is_online'], 'bot said: ' . $this->reply());
        $this->assertStringContainsString('ONLINE', $this->reply());

        // a suspended driver cannot go online this way either
        $driver->forceFill(['suspended_at' => now()])->saveQuietly();
        Cache::put("driver_status_{$driver->id}", ['is_online' => false], now()->addDay());
        if (method_exists($driver->fresh(), 'isSuspended') && $driver->fresh()->isSuspended()) {
            $this->say('919111111111', 'online');
            $this->assertFalse(Cache::get("driver_status_{$driver->id}")['is_online']);
            $this->assertStringContainsString('suspended', strtolower($this->reply()));
        }
    }
}
