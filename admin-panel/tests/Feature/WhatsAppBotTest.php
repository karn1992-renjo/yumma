<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The customer chat assistant, driven through the real signed webhook. */
class WhatsAppBotTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '919876543210';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]])]);
    }

    private function say(string $text, string $type = 'text', ?array $location = null): void
    {
        $message = ['id' => 'wamid.' . uniqid(), 'from' => self::FROM, 'type' => $type];
        $type === 'location' ? $message['location'] = $location : $message['text'] = ['body' => $text];
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => self::FROM, 'profile' => ['name' => 'Asha']]], 'messages' => [$message],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    private function lastReply(): string
    {
        return (string) WhatsAppMessage::where('direction', 'out')->latest('id')->value('body');
    }

    private function restaurant(array $override = []): Restaurant
    {
        return Restaurant::create($override + [
            'owner_id' => User::factory()->create()->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub-' . uniqid(), 'address' => 'Main Street',
            'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001',
            'email' => uniqid('r') . '@example.com', 'cuisine' => ['Indian'], 'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0,
            'delivery_fee' => 0, 'delivery_time' => 30, 'delivery_radius' => 8, 'rating' => 4.5, 'total_ratings' => 3, 'is_featured' => false, 'is_verified' => true,
        ]);
    }

    private function order(User $user, Restaurant $restaurant, array $override = []): Order
    {
        return Order::withoutEvents(fn () => Order::create($override + [
            'order_number' => 'ORD20261023' . random_int(1000, 9999), 'customer_id' => $user->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery',
            'items' => [], 'subtotal' => 250, 'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 250,
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'on_the_way', 'delivery_otp' => '4321', 'customer_name' => $user->name,
            'customer_phone' => '9876543210', 'customer_address' => ['address' => 'Main Street'], 'delivery_address' => 'Main Street',
            'delivery_lat' => 22.57, 'delivery_lng' => 88.36,
        ]));
    }

    public function test_hello_shows_the_menu_and_unknown_text_gets_a_helpful_reply(): void
    {
        $this->say('Hi');
        $this->assertStringContainsString('Track my order', $this->lastReply());
        $this->assertStringContainsString('Welcome', $this->lastReply());

        $this->say('blah blah');
        $this->assertStringContainsString("didn't get that", $this->lastReply());
    }

    public function test_track_shows_status_rider_otp_and_only_the_customers_own_orders(): void
    {
        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $rider = User::factory()->create(['name' => 'Imran', 'phone' => '9111111111']);
        $restaurant = $this->restaurant();
        $mine = $this->order($user, $restaurant, ['driver_id' => $rider->id]);
        $stranger = User::factory()->create(['phone' => '9000000099']);
        $theirs = $this->order($stranger, $restaurant, ['order_number' => 'ORD99999999', 'customer_phone' => '9000000099']);

        $this->say('1');
        $reply = $this->lastReply();
        $this->assertStringContainsString($mine->order_number, $reply);
        $this->assertStringContainsString('On The Way', $reply);
        $this->assertStringContainsString('Imran', $reply);
        $this->assertStringContainsString('4321', $reply);

        $this->say('where is ' . $theirs->order_number);               // someone else's order
        $this->assertStringContainsString("couldn't find order", $this->lastReply());
        $this->assertStringNotContainsString('Delivery OTP', $this->lastReply());
    }

    public function test_recent_orders_list_then_pick_one(): void
    {
        $user = User::factory()->create(['phone' => '9876543210']);
        $restaurant = $this->restaurant();
        $this->order($user, $restaurant, ['status' => 'delivered', 'order_number' => 'ORD11111111']);
        $this->order($user, $restaurant, ['status' => 'cancelled', 'order_number' => 'ORD22222222', 'cancellation_reason' => 'Closed']);

        $this->say('2');
        $this->assertStringContainsString('ORD22222222', $this->lastReply());
        $this->assertStringContainsString('ORD11111111', $this->lastReply());

        $this->say('1');                                               // newest first = cancelled
        $this->assertStringContainsString('Cancelled', $this->lastReply());
        $this->assertStringContainsString('Closed', $this->lastReply());
    }

    public function test_account_and_offers(): void
    {
        $this->say('4');
        $this->assertStringContainsString("can't find an account", $this->lastReply());

        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210', 'reward_points_balance' => 120]);
        $this->say('wallet');
        $this->assertStringContainsString('Asha Verma', $this->lastReply());
        $this->assertStringContainsString('120', $this->lastReply());

        \App\Models\PromoCode::create(['code' => 'WELCOME50', 'title' => 'Welcome', 'discount_type' => 'percentage', 'discount_value' => 50,
            'min_order_amount' => 199, 'is_active' => true, 'created_by_type' => 'admin', 'audience_type' => 'all',
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth()]);
        $this->say('5');
        $this->assertStringContainsString('WELCOME50', $this->lastReply());
    }

    public function test_location_leads_to_nearby_restaurants_and_a_menu_with_a_secure_order_link(): void
    {
        $near = $this->restaurant(['name' => 'Near Kitchen']);
        $this->restaurant(['name' => 'Far Away Diner', 'latitude' => 28.6, 'longitude' => 77.2]);
        MenuItem::create(['restaurant_id' => $near->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true, 'is_veg' => true]);

        $this->say('3');
        $this->assertStringContainsString('Location', $this->lastReply());

        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->assertStringContainsString('Near Kitchen', $this->lastReply());
        $this->assertStringNotContainsString('Far Away', $this->lastReply());

        $this->say('1');
        $this->assertStringContainsString('Paneer Tikka', $this->lastReply());
        $this->assertStringContainsString('To add: reply the item number', $this->lastReply());   // an interactive menu now, not just a web link
        $this->assertStringContainsString('CHECKOUT', $this->lastReply());
    }

    public function test_human_handoff_pauses_the_bot_until_menu_and_stop_unsubscribes(): void
    {
        $this->say('6');
        $this->assertStringContainsString('connecting you', $this->lastReply());
        $count = WhatsAppMessage::where('direction', 'out')->count();

        $this->say('my food was cold');                                // agent's job now: the bot stays quiet
        $this->say('still waiting');
        $this->assertSame($count, WhatsAppMessage::where('direction', 'out')->count());       // the handoff message was the acknowledgement

        $this->say('menu');
        $this->assertStringContainsString('Track my order', $this->lastReply());

        $this->say('STOP');
        $this->assertFalse(WhatsAppContact::where('phone', '+' . self::FROM)->first()->opted_in);
        $this->assertStringContainsString('unsubscribed', $this->lastReply());
    }
}
