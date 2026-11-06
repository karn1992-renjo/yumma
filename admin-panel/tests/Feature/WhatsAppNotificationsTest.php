<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Orders going through their lifecycle produce the right WhatsApp templates, once each. */
class WhatsAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function enable(bool $providerUp = true): void
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => $providerUp
            ? Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]])
            : Http::response(['error' => ['message' => 'boom']], 500)]);
    }

    private function order(array $override = []): Order
    {
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub-' . uniqid(),
            'address' => 'Main Street', 'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.57, 'longitude' => 88.36,
            'phone' => '9000000001', 'email' => uniqid('r') . '@example.com', 'cuisine' => ['Indian'], 'is_open' => true, 'is_pure_veg' => false,
            'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true,
        ]);
        $customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);

        return Order::create($override + [
            'order_number' => 'ORD' . uniqid(), 'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery',
            'items' => [['name' => 'Paneer', 'quantity' => 2]], 'subtotal' => 250, 'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0,
            'total' => 250, 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'customer_name' => 'Asha Verma',
            'customer_phone' => '9876543210', 'customer_address' => ['address' => 'Main Street', 'phone' => '9876543210'],
            'delivery_address' => 'Main Street', 'delivery_lat' => 22.57, 'delivery_lng' => 88.36,
        ]);
    }

    private function events(): array
    {
        return WhatsAppMessage::where('direction', 'out')->orderBy('id')->pluck('event')->all();
    }

    public function test_nothing_is_sent_while_whatsapp_is_off(): void
    {
        Http::fake();
        $order = $this->order();
        $order->update(['status' => 'delivered']);

        $this->assertSame([], $this->events());
        Http::assertNothingSent();
    }

    public function test_cod_order_lifecycle_sends_each_message_once(): void
    {
        $this->enable();
        $order = $this->order();                                  // COD -> placed at creation
        $this->assertSame(['order_placed', 'restaurant_new_order'], $this->events());

        $order->update(['status' => 'confirmed']);
        $driver = User::factory()->create(['name' => 'Imran', 'phone' => '9111111111']);
        $driver->assignRole(\Spatie\Permission\Models\Role::findOrCreate('delivery_partner', 'web'));
        $order->update(['driver_id' => $driver->id]);
        $order->update(['status' => 'picked_up', 'delivery_otp' => '4321']);
        $order->update(['status' => 'on_the_way']);               // same template again -> deduplicated
        $order->update(['status' => 'delivered']);

        $this->assertSame([
            'order_placed', 'restaurant_new_order', 'order_accepted', 'order_rider_assigned', 'restaurant_rider_assigned', 'driver_new_request',
            'order_out_for_delivery', 'order_delivered',
        ], $this->events());

        $out = WhatsAppMessage::where('event', 'order_out_for_delivery')->first();
        $this->assertStringContainsString('4321', $out->body);
        $this->assertSame('sent', $out->status);
        $this->assertSame($order->id, $out->related_id);
    }

    public function test_online_order_is_announced_only_after_payment_and_cancel_sends_reason(): void
    {
        $this->enable();
        $order = $this->order(['payment_method' => 'razorpay', 'payment_status' => 'pending']);
        $this->assertSame([], $this->events());                   // unpaid online order: nothing yet

        $order->update(['payment_status' => 'success']);
        $this->assertSame(['order_placed', 'restaurant_new_order'], $this->events());

        $order->update(['status' => 'cancelled', 'cancellation_reason' => 'Restaurant is closed']);
        $this->assertStringContainsString('Restaurant is closed', WhatsAppMessage::where('event', 'order_cancelled')->value('body'));
    }

    public function test_switching_a_message_type_off_and_customer_preference_are_respected(): void
    {
        $this->enable();
        AppSetting::setValue('whatsapp_event_order_delivered', '0');
        $order = $this->order();
        $order->customer->update(['notify_order_updates' => false]);

        $order->update(['status' => 'delivered']);
        $this->assertNotContains('order_delivered', $this->events());
        // customer opted out of order updates, restaurant still gets its own new-order message
        $this->assertNotContains('order_accepted', $this->events());
    }

    public function test_refund_message_and_failures_never_break_the_order(): void
    {
        $this->enable(providerUp: false);

        $order = $this->order();                                  // provider down: order still saves
        $this->assertNotNull($order->id);
        $this->assertSame('failed', WhatsAppMessage::where('event', 'order_placed')->value('status'));

        app(WhatsAppNotifier::class)->refundIssued($order, 100);
        $this->assertContains('refund_issued', $this->events());
    }
}
