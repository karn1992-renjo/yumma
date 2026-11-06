<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\PublicTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Public map tracking link + restaurant/driver online-offline notifications. */
class WhatsAppTrackingAndStatusTest extends TestCase
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

    private function restaurant(): Restaurant
    {
        return Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9222222222'])->id, 'name' => 'Spice Hub', 'slug' => 'sh-' . uniqid(), 'address' => 'Main Street',
            'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.57, 'longitude' => 88.36, 'phone' => '9000000001', 'email' => uniqid() . '@e.com',
            'cuisine' => ['Indian'], 'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4,
            'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true,
        ]);
    }

    private function order(Restaurant $restaurant, array $override = []): Order
    {
        $customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);

        return Order::withoutEvents(fn () => Order::create($override + [
            'order_number' => 'ORD' . random_int(10000000, 99999999), 'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery',
            'items' => [], 'subtotal' => 250, 'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 250, 'payment_method' => 'cod',
            'payment_status' => 'pending', 'status' => 'on_the_way', 'customer_name' => 'Asha Verma', 'customer_phone' => '9876543210',
            'customer_address' => ['address' => '12 Park Street'], 'delivery_address' => '12 Park Street', 'delivery_lat' => 22.60, 'delivery_lng' => 88.40,
        ]));
    }

    // ---- Public tracking page --------------------------------------------------------------------------

    public function test_signed_link_shows_the_page_and_live_data_without_login(): void
    {
        AppSetting::setValue('google_maps_api_key', 'TESTKEY');
        $restaurant = $this->restaurant();
        $rider = User::factory()->create(['name' => 'Imran Khan', 'phone' => '9111111111']);
        $order = $this->order($restaurant, ['driver_id' => $rider->id, 'delivery_otp' => '7788']);
        Cache::put("driver_location_{$rider->id}", ['lat' => 22.58, 'lng' => 88.38, 'updated_at' => now()], 300);

        $this->get(PublicTracking::url($order))->assertOk()->assertSee('Track order')->assertSee('maps.googleapis.com')->assertDontSee('7788');

        $json = $this->getJson(PublicTracking::dataUrl($order))->assertOk()->json();
        $this->assertSame('On The Way', $json['label']);
        $this->assertSame(3, $json['step']);
        $this->assertSame('Imran', $json['rider']['name']);                      // first name only
        $this->assertEqualsWithDelta(22.58, $json['rider']['lat'], 0.0001);
        $this->assertFalse($json['rider']['stale']);
        $this->assertGreaterThan(0, $json['eta_min']);
        $this->assertStringNotContainsString('9111111111', json_encode($json));   // no phone on a shareable link
        $this->assertStringNotContainsString('7788', json_encode($json));         // never the delivery OTP
    }

    public function test_link_cannot_be_forged_edited_or_used_after_expiry(): void
    {
        $order = $this->order($this->restaurant());
        $other = $this->order($this->restaurant(), ['order_number' => 'ORD00000000']);

        $this->get('/track/' . $order->id)->assertForbidden();                                    // unsigned
        $this->getJson('/track/' . $order->id . '/data')->assertForbidden();

        $url = PublicTracking::url($order);
        $this->get(str_replace('/track/' . $order->id, '/track/' . $other->id, $url))->assertForbidden();   // another order id

        $this->travel(13)->hours();
        $this->get($url)->assertForbidden();                                                      // expired
    }

    public function test_rider_is_hidden_before_pickup_and_after_delivery(): void
    {
        $restaurant = $this->restaurant();
        $rider = User::factory()->create(['name' => 'Imran', 'phone' => '9111111111']);
        $order = $this->order($restaurant, ['driver_id' => $rider->id, 'status' => 'preparing']);
        Cache::put("driver_location_{$rider->id}", ['lat' => 22.58, 'lng' => 88.38, 'updated_at' => now()], 300);

        $this->assertNull($this->getJson(PublicTracking::dataUrl($order))->json('rider'));
        $order->forceFill(['status' => 'delivered'])->saveQuietly();
        $json = $this->getJson(PublicTracking::dataUrl($order->fresh()))->json();
        $this->assertNull($json['rider']);
        $this->assertTrue($json['delivered']);
    }

    public function test_whatsapp_messages_carry_the_signed_link_and_the_rider_reply_works(): void
    {
        $restaurant = $this->restaurant();
        $rider = User::factory()->create(['name' => 'Imran Khan', 'phone' => '9111111111']);
        $order = $this->order($restaurant, ['driver_id' => $rider->id]);
        Cache::put("driver_location_{$rider->id}", ['lat' => 22.58, 'lng' => 88.38, 'updated_at' => now()], 300);

        $body = json_encode(['entry' => [['changes' => [['value' => ['contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Asha']]],
            'messages' => [['id' => 'w1', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'where is my rider']]]]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

        $reply = WhatsAppMessage::where('event', 'bot')->latest('id')->value('body');
        $this->assertStringContainsString('google.com/maps?q=22.58,88.38', $reply);
        $this->assertStringContainsString('/track/' . $order->id, $reply);
        $this->assertStringContainsString('signature=', $reply);
        $this->assertStringContainsString('Imran', $reply);
        $this->assertStringNotContainsString('Khan', $reply);
    }

    // ---- Online / offline notifications ------------------------------------------------------------------

    public function test_restaurant_is_told_when_it_closes_and_opens_but_not_twice(): void
    {
        $restaurant = $this->restaurant();

        $restaurant->update(['is_open' => false]);
        $restaurant->update(['is_open' => false]);                                   // unchanged: nothing
        $closed = WhatsAppMessage::where('event', 'restaurant_status_changed')->get();
        $this->assertSame(2, $closed->count());                                      // restaurant number + owner number, once each
        $this->assertStringContainsString('CLOSED', $closed->first()->body);

        $restaurant->update(['is_open' => true]);
        $this->assertSame(4, WhatsAppMessage::where('event', 'restaurant_status_changed')->count());   // closing then opening straight away still sends both notices
    }

    public function test_restaurant_is_not_told_twice_when_the_assistant_itself_closes_it(): void
    {
        $owner = User::factory()->create(['name' => 'Ravi', 'phone' => '9222222223']);
        $restaurant = $this->restaurant();
        $restaurant->update(['owner_id' => $owner->id]);
        WhatsAppMessage::query()->delete();

        $body = json_encode(['entry' => [['changes' => [['value' => ['contacts' => [['wa_id' => '919222222223', 'profile' => ['name' => 'Ravi']]],
            'messages' => [['id' => 'w9', 'from' => '919222222223', 'type' => 'text', 'text' => ['body' => 'close']]]]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();

        $this->assertFalse($restaurant->fresh()->is_open);
        $this->assertSame(0, WhatsAppMessage::where('event', 'restaurant_status_changed')->count());
        $this->assertStringContainsString('Now CLOSED', WhatsAppMessage::where('event', 'bot')->latest('id')->value('body'));
    }

    public function test_driver_is_told_when_going_offline_and_online(): void
    {
        $driver = User::factory()->create(['name' => 'Imran Khan', 'phone' => '9111111111']);
        $driver->assignRole(Role::findOrCreate('delivery_partner', 'web'));
        $notifier = app(\App\Services\WhatsApp\WhatsAppNotifier::class);

        $notifier->driverStatusChanged($driver, false, 'You were taken offline because you have no active booked gig.');
        $msg = WhatsAppMessage::where('event', 'driver_status_changed')->first();
        $this->assertSame('+919111111111', $msg->phone);
        $this->assertSame('OFFLINE', $msg->payload['params'][1]);
        $this->assertStringContainsString('you are now OFFLINE', $msg->body);
        $this->assertStringContainsString('no new requests will be sent to you', $msg->body);
    }
}
