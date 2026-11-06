<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\PublicPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A customer orders, pays and tracks entirely inside WhatsApp. */
class WhatsAppOrderingTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '919876543210';

    private Restaurant $restaurant;
    private MenuItem $paneer;
    private MenuItem $biryani;
    private MenuItem $pizza;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(13, 0));   // restaurants are open at midday whatever time the suite runs
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]])]);
        Role::findOrCreate('customer', 'web');

        $this->customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210', 'email' => 'asha@example.com']);
        $this->customer->assignRole('customer');

        $this->restaurant = Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9222222222'])->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'Main Street',
            'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001',
            'email' => 'sh@example.com', 'cuisine' => ['Indian'], 'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0,
            'delivery_time' => 30, 'delivery_radius' => 8, 'rating' => 4.5, 'total_ratings' => 3, 'is_featured' => false, 'is_verified' => true,
        ]);
        $this->paneer = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true, 'is_veg' => true, 'is_bestseller' => true]);
        $this->biryani = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Chicken Biryani', 'price' => 300, 'is_available' => true, 'is_veg' => false]);
        $this->pizza = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Margherita Pizza', 'price' => 250, 'is_available' => true, 'is_veg' => true,
            'variants' => [['id' => 'm', 'name' => 'Medium', 'price' => 0], ['id' => 'l', 'name' => 'Large', 'price' => 100]]]);
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

    private function reply(): string
    {
        return (string) WhatsAppMessage::where('direction', 'out')->where('event', 'bot')->latest('id')->value('body');
    }

    /** Walks the customer from "3" to the restaurant's menu. */
    private function openMenu(): void
    {
        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->assertStringContainsString('Spice Hub', $this->reply());
        $this->say('1');
    }

    public function test_the_menu_lists_items_and_marks_the_ones_that_need_options(): void
    {
        $this->openMenu();
        $menu = $this->reply();
        $this->assertStringContainsString('Paneer Tikka – ', $menu);
        $this->assertStringContainsString('Chicken Biryani', $menu);
        $this->assertStringContainsString('choose options in the app', $menu);     // the pizza with sizes
        $this->assertStringContainsString('CHECKOUT', $menu);

        $this->say('search biryani');
        $this->assertStringContainsString('Chicken Biryani', $this->reply());
        $this->assertStringNotContainsString('Paneer Tikka', $this->reply());
    }

    public function test_complete_cash_on_delivery_order_from_menu_to_tracking(): void
    {
        $this->openMenu();

        $this->say('1x2');                                        // 2 x Paneer Tikka (best seller is first)
        $this->assertStringContainsString('Added 2 × Paneer Tikka', $this->reply());
        $this->say('cart');
        $this->assertStringContainsString('2 × Paneer Tikka – ', $this->reply());

        $this->say('checkout');
        $this->assertStringContainsString('Where should we deliver', $this->reply());
        $this->say('Flat 4B, Lake View Apartments, near City Mall, Kolkata');
        $this->assertStringContainsString('coupon', $this->reply());
        $this->say('skip');
        $this->assertStringContainsString('How would you like to pay', $this->reply());
        $this->assertStringContainsString('Cash on delivery', $this->reply());
        $this->say('1');

        $confirm = $this->reply();
        $this->assertStringContainsString('Please confirm your order', $confirm);
        $this->assertStringContainsString('2 × Paneer Tikka', $confirm);
        $this->assertStringContainsString('Total:', $confirm);
        $this->assertStringContainsString('Flat 4B', $confirm);
        $this->assertSame(0, Order::count());                     // nothing is created before YES

        $this->say('yes');

        $order = Order::firstOrFail();
        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertSame($this->restaurant->id, $order->restaurant_id);
        $this->assertSame('cod', $order->payment_method);
        $this->assertStringContainsString('Flat 4B', (string) $order->delivery_address);
        $this->assertEquals(2, collect($order->items)->sum('quantity'));
        $this->assertEqualsWithDelta(22.573, (float) $order->delivery_lat, 0.0001);
        $this->assertGreaterThanOrEqual(440, (float) $order->total);

        $final = $this->reply();
        $this->assertStringContainsString("Order placed! #{$order->order_number}", $final);
        $this->assertStringContainsString('/track/' . $order->id, $final);       // live map link, no login needed
        $this->assertStringContainsString('signature=', $final);

        // the restaurant is told; the customer is not sent a second "order placed" template on top of the chat
        $events = WhatsAppMessage::where('direction', 'out')->pluck('event')->all();
        $this->assertContains('restaurant_new_order', $events);
        $this->assertNotContains('order_placed', $events);

        // cart is gone, and tracking keeps working in the same chat
        $this->say('1');
        $this->assertStringContainsString($order->order_number, $this->reply());
        $this->say('where is my rider');
        $this->assertStringContainsString('/track/' . $order->id, $this->reply());
    }

    public function test_options_items_empty_cart_and_unregistered_numbers_are_handled(): void
    {
        $this->openMenu();

        $this->say('checkout');                                    // empty cart
        $this->assertStringContainsString('cart is empty', $this->reply());

        $this->say('3');                                           // the pizza has sizes: cannot be chosen in chat
        $this->assertStringContainsString('options that can only be chosen in the app', $this->reply(), 'bot said: ' . $this->reply());

        $this->say('9');                                           // not on the page
        $this->assertStringContainsString('not on this page', $this->reply());

        $this->say('1');
        $this->say('menu');                                        // leaving the flow keeps the cart
        $this->assertStringContainsString('item(s) from Spice Hub in your cart', $this->reply());
        $this->say('cart');
        $this->assertStringContainsString('1 × Paneer Tikka', $this->reply());
        $this->say('clear');
        $this->assertStringContainsString('cart is empty', $this->reply());
    }

    public function test_a_number_without_an_account_cannot_check_out(): void
    {
        $this->customer->update(['phone' => '9000000077']);        // WhatsApp number no longer matches any account
        \App\Models\WhatsAppContact::query()->delete();
        $this->openMenu();
        $this->say('1');
        $this->say('checkout');
        $this->assertStringContainsString('create a new account', $this->reply());    // offered OTP sign-up / login instead of a dead end
        $this->assertStringContainsString('log in to an account you already have', $this->reply());
        $this->assertSame(0, Order::count());
    }

    public function test_declining_at_the_confirmation_keeps_the_cart_and_creates_nothing(): void
    {
        $this->openMenu();
        $this->say('2');
        $this->say('checkout');
        $this->say('Flat 4B, Lake View Apartments, near City Mall');
        $this->say('skip');
        $this->say('1');
        $this->say('no');

        $this->assertSame(0, Order::count());
        $this->assertStringContainsString('nothing was ordered', $this->reply());
        $this->say('cart');
        $this->assertStringContainsString('Chicken Biryani', $this->reply());
    }

    public function test_online_payment_is_pay_first_and_the_order_appears_only_after_payment(): void
    {
        AppSetting::setValue('payment_gateway_enabled', '1');
        AppSetting::setValue('enabled_payment_gateways', json_encode(['razorpay']));
        AppSetting::setValue('razorpay_key', 'rzp_test_key');
        AppSetting::setValue('razorpay_secret', 'rzp_secret');
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_WA1', 'amount' => 66000, 'currency' => 'INR']),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]]),
        ]);

        $this->openMenu();
        $this->say('1x3');                                         // 3 x 220 = 660
        $this->say('checkout');
        $this->say('Flat 4B, Lake View Apartments, near City Mall');
        $this->say('skip');
        $this->assertStringContainsString('Pay online', $this->reply());
        $this->say('2');
        $this->assertStringContainsString('Please confirm your order', $this->reply());
        $this->say('yes');

        // a pay link, no order yet
        $msg = $this->reply();
        $this->assertStringContainsString('/pay/', $msg);
        $this->assertSame(0, Order::count());
        preg_match('#https?://\S+/pay/[A-Za-z0-9]+\?\S+#', $msg, $m);
        $link = rtrim($m[0], '.,)');

        $this->get($link)->assertOk()->assertSee('Pay now')->assertSee('Spice Hub')->assertSee('Secure payment by Razorpay');
        $this->get(str_replace('/pay/', '/pay/x', $link))->assertForbidden();             // tampered link

        // the page starts the payment through the app's own checkout code
        $token = basename(parse_url($link, PHP_URL_PATH));
        $start = $this->postJson(PublicPayment::startUrl($token))->assertOk()->json();
        $this->assertSame('order_WA1', $start['data']['order_id']);
        $this->assertSame('razorpay', $start['data']['gateway']);
        $this->assertSame(0, Order::count());                                             // still unpaid -> still no order

        // customer pays; the page sends the proof back
        $payment = 'pay_WA1';
        $proof = ['payment_id' => $payment, 'razorpay_order_id' => 'order_WA1', 'razorpay_signature' => hash_hmac('sha256', "order_WA1|{$payment}", 'rzp_secret')];
        $this->postJson(PublicPayment::completeUrl($token), array_merge($proof, ['razorpay_signature' => 'forged']))->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertSame(0, Order::count());

        $done = $this->postJson(PublicPayment::completeUrl($token), $proof)->assertOk()->json();
        $order = Order::firstOrFail();
        $this->assertTrue($done['success']);
        $this->assertSame($order->order_number, $done['order_number']);
        $this->assertSame('success', $order->payment_status);
        $this->assertSame('razorpay', $order->payment_method);
        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertEquals(3, collect($order->items)->sum('quantity'));
        $this->assertStringContainsString('/track/' . $order->id, $done['tracking_url']);

        // the chat confirms it, the cart is emptied and the link cannot be used twice
        $this->assertStringContainsString("Order #{$order->order_number} is confirmed", $this->reply());
        $this->postJson(PublicPayment::completeUrl($token), $proof)->assertStatus(410);
        $this->say('cart');
        $this->assertStringNotContainsString('Paneer Tikka', $this->reply());
    }

    public function test_an_expired_payment_link_is_explained_and_never_charges(): void
    {
        AppSetting::setValue('payment_gateway_enabled', '1');
        AppSetting::setValue('enabled_payment_gateways', json_encode(['razorpay']));
        AppSetting::setValue('razorpay_key', 'k');
        AppSetting::setValue('razorpay_secret', 's');

        $link = PublicPayment::createSession($this->customer, ['restaurant_id' => $this->restaurant->id], 100.0, 'Spice Hub', '+919876543210');
        $token = basename(parse_url($link, PHP_URL_PATH));
        PublicPayment::forget($token);

        $this->get($link)->assertOk()->assertSee('expired');
        $this->postJson(PublicPayment::startUrl($token))->assertStatus(410);
        $this->assertSame(0, Order::count());
    }
}
