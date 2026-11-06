<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Address;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** OTP login / sign-up in the chat, and sharing the current location for delivery. */
class WhatsAppLoginAndLocationTest extends TestCase
{
    use RefreshDatabase;

    private string $from = '919876543210';
    private ?string $lastOtp = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(13, 0));   // restaurants are open at midday whatever time the suite runs
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        // the SMS provider that carries the OTP
        AppSetting::setValue('otp_service_provider', 'twilio');
        AppSetting::setValue('twilio_account_sid', 'AC1');
        AppSetting::setValue('twilio_auth_token', 'tok');
        AppSetting::setValue('twilio_phone_number', '+14155238886');
        Role::findOrCreate('customer', 'web');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]]),
            'api.twilio.com/*' => function (HttpRequest $request) {
                if (preg_match('/OTP\+code\+is\+(\d{6})/', $request->body(), $m)) {
                    $this->lastOtp = $m[1];
                }

                return Http::response(['sid' => 'SM1']);
            },
            'maps.googleapis.com/*' => Http::response(['results' => [['formatted_address' => 'Lake View Apartments, Park Street, Kolkata 700016']]]),
        ]);
    }

    private function say(string $text, string $type = 'text', ?array $location = null): void
    {
        $message = ['id' => 'wamid.' . uniqid(), 'from' => $this->from, 'type' => $type];
        $type === 'location' ? $message['location'] = $location : $message['text'] = ['body' => $text];
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $this->from, 'profile' => ['name' => 'Asha']]], 'messages' => [$message],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    private function reply(): string
    {
        return (string) WhatsAppMessage::where('direction', 'out')->where('event', 'bot')->latest('id')->value('body');
    }

    // ---- Sign up and log in with an OTP ---------------------------------------------------------------------

    public function test_a_new_customer_signs_up_in_the_chat_with_an_sms_code(): void
    {
        $this->say('signup');
        $this->assertStringContainsString('What is your name', $this->reply());

        $this->say('Asha Verma');
        $this->assertStringContainsString('6-digit code by SMS', $this->reply());
        $this->assertNotNull($this->lastOtp);                                      // the SMS really went out
        $this->assertSame(0, User::where('phone', '+919876543210')->count());       // no account before the code

        $this->say('000000');                                                      // wrong code
        $this->assertStringContainsString('Invalid or expired OTP', $this->reply());
        $this->assertSame(0, User::where('phone', '+919876543210')->count());

        $this->say($this->lastOtp);
        $user = User::where('phone', '+919876543210')->firstOrFail();
        $this->assertSame('Asha Verma', $user->name);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame($user->id, WhatsAppContact::where('phone', '+919876543210')->value('user_id'));
        $this->assertStringContainsString('Welcome, Asha Verma', $this->reply());
        $this->assertSame(0, $user->tokens()->count());                            // the unused app token was thrown away

        // and the new account works straight away
        $this->say('4');
        $this->assertStringContainsString('Asha Verma', $this->reply());
    }

    public function test_an_existing_customer_with_another_number_logs_in_with_an_otp(): void
    {
        $account = User::factory()->create(['name' => 'Ravi Kumar', 'phone' => '9123456789']);
        $account->assignRole('customer');

        $this->say('login');
        $this->assertStringContainsString('registered with', $this->reply());
        $this->say('9123456789');
        $this->assertStringContainsString('code by SMS to', $this->reply());
        $this->assertSame(null, WhatsAppContact::where('phone', '+919876543210')->value('user_id'));   // not linked before the code

        $this->say($this->lastOtp);
        $this->assertStringContainsString('Logged in as Ravi Kumar', $this->reply());
        $this->assertSame($account->id, WhatsAppContact::where('phone', '+919876543210')->value('user_id'));
        $this->assertSame(0, $account->tokens()->count());

        // an unknown number is not logged in and gets a way forward
        WhatsAppContact::where('phone', '+919876543210')->update(['user_id' => null]);
        $this->say('login');
        $this->say('9000000099');
        $this->assertStringContainsString('no account with', strtolower($this->reply()));
    }

    public function test_wrong_codes_are_limited_and_resend_works(): void
    {
        $account = User::factory()->create(['phone' => '9123456789']);
        $account->assignRole('customer');
        $this->say('login');
        $this->say('9123456789');
        $first = $this->lastOtp;

        $this->say('resend');
        $this->assertNotNull($this->lastOtp);

        for ($i = 0; $i < 5; $i++) {
            $this->say('111111');
        }
        $this->assertStringContainsString('Too many wrong codes', $this->reply());
        $this->assertNull(WhatsAppContact::where('phone', '+919876543210')->value('user_id'));
        $this->say($this->lastOtp);                                                // the flow was closed: a late right code does nothing
        $this->assertNull(WhatsAppContact::where('phone', '+919876543210')->value('user_id'));
    }

    public function test_checkout_without_an_account_offers_login_and_keeps_the_cart(): void
    {
        [$restaurant] = $this->restaurantWithMenu();

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');
        $this->say('1');
        $this->say('checkout');
        $this->assertStringContainsString('1️⃣ to create a new account', $this->reply());
        $this->say('1');
        $this->say('Asha Verma');
        $this->say($this->lastOtp);

        $this->assertStringContainsString('Your cart is saved. Reply CHECKOUT', $this->reply());
        $this->say('checkout');
        $this->assertStringContainsString('deliver', strtolower($this->reply()));
    }

    // ---- Current location for delivery ------------------------------------------------------------------------------

    private function restaurantWithMenu(): array
    {
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9222222222'])->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'Main Street', 'city' => 'Kolkata',
            'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'],
            'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'delivery_radius' => 8, 'rating' => 4.5,
            'total_ratings' => 3, 'is_featured' => false, 'is_verified' => true,
        ]);
        $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true, 'is_veg' => true, 'is_bestseller' => true]);

        return [$restaurant, $item];
    }

    private function customer(): User
    {
        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $user->assignRole('customer');

        return $user;
    }

    public function test_the_current_location_pin_becomes_the_delivery_location_at_any_step(): void
    {
        AppSetting::setValue('google_maps_api_key', 'MAPSKEY');
        $user = $this->customer();
        $this->restaurantWithMenu();

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);   // used to find restaurants
        $this->say('1');
        $this->say('1x2');
        $this->say('checkout');
        $this->assertStringContainsString('DETECT', $this->reply());

        // a NEW pin at the address step: the address is read from it and "OK" accepts it
        $this->say('', 'location', ['latitude' => 22.5800, 'longitude' => 88.3700]);
        $this->assertStringContainsString('Got your current location', $this->reply());
        $this->assertStringContainsString('google.com/maps?q=22.58,88.37', $this->reply());
        $this->assertStringContainsString('Lake View Apartments', $this->reply());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'maps.googleapis.com/maps/api/geocode') && str_contains($r->url(), 'latlng=22.58%2C88.37'));

        $this->say('ok');
        $this->assertStringContainsString('coupon', $this->reply());
        $this->say('skip');
        $this->say('1');                                                             // cash on delivery

        // the confirmation shows the pin, and a different pin at this late step re-prices and re-confirms
        $this->assertStringContainsString('Delivery pin: https://www.google.com/maps?q=22.58,88.37', $this->reply());
        $this->say('', 'location', ['latitude' => 22.5900, 'longitude' => 88.3800]);
        $this->assertStringContainsString('Delivery location updated', WhatsAppMessage::where('event', 'bot')->orderByDesc('id')->skip(1)->value('body'));
        $this->assertStringContainsString('Please confirm your order', $this->reply());
        $this->assertStringContainsString('q=22.59,88.38', $this->reply());

        $this->say('yes');
        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->customer_id);
        $this->assertEqualsWithDelta(22.59, (float) $order->delivery_lat, 0.0001);   // the latest pin was delivered to
        $this->assertEqualsWithDelta(88.38, (float) $order->delivery_lng, 0.0001);
        $this->assertStringContainsString('Lake View Apartments', (string) $order->delivery_address);
    }

    public function test_sharing_a_pin_overrides_a_saved_address_location(): void
    {
        $user = $this->customer();
        Address::create(['user_id' => $user->id, 'name' => 'Home', 'address' => 'Flat 9, Green Park', 'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001',
            'phone' => '9876543210', 'is_default' => true, 'latitude' => 22.5000, 'longitude' => 88.3000]);
        $this->restaurantWithMenu();

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');
        $this->say('1');
        $this->say('checkout');
        $this->assertStringContainsString('SAVED', $this->reply());
        $this->say('1');                                                             // the saved Home address
        $this->say('', 'location', ['latitude' => 22.5750, 'longitude' => 88.3660]);  // ... but I'm somewhere else right now
        $this->say('skip');
        $this->say('1');
        $this->say('yes');

        $order = Order::firstOrFail();
        $this->assertEqualsWithDelta(22.575, (float) $order->delivery_lat, 0.0001);   // the pin, not Home's coordinates
        $this->assertStringContainsString('Flat 9, Green Park', (string) $order->delivery_address);
    }

    public function test_a_pin_while_browsing_is_saved_for_checkout(): void
    {
        $this->customer();
        $this->restaurantWithMenu();

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');
        $this->say('', 'location', ['latitude' => 22.5810, 'longitude' => 88.3710]);
        $this->assertStringContainsString('Saved as your delivery location', $this->reply());
        $this->say('1');
        $this->say('checkout');
        $this->say('Flat 4B, Lake View Apartments, near City Mall');
        $this->say('skip');
        $this->say('1');
        $this->say('yes');

        $this->assertEqualsWithDelta(22.581, (float) Order::firstOrFail()->delivery_lat, 0.0001);
    }

    // ---- A registered number needs no login -----------------------------------------------------------------------

    public function test_a_registered_number_is_recognised_however_it_was_typed_and_never_asked_to_log_in(): void
    {
        // the same kind of number, saved six different ways when each customer registered
        $stored = [
            'Anil' => '9876540001', 'Bina' => '+91 98765 40002', 'Chitra' => '98765-40003',
            'Dev' => '919876540004', 'Esha' => '+91-9876540005', 'Farid' => '09876540006',
        ];

        $n = 0;
        foreach ($stored as $name => $format) {
            $n++;
            $user = User::factory()->create(['name' => "{$name} Kumar", 'phone' => $format]);
            $user->assignRole('customer');
            $this->from = '91987654000' . $n;                // the WhatsApp number the same person writes from

            $this->say('hi');
            $this->assertStringContainsString("Hi {$name}!", $this->reply(), "a number saved as '{$format}' was not recognised");
            $this->assertSame($user->id, WhatsAppContact::where('phone', '+' . $this->from)->value('user_id'));
        }
        $this->assertNull($this->lastOtp);                                           // nobody was asked for a code
    }

    public function test_checkout_for_a_registered_number_goes_straight_to_delivery_with_no_otp(): void
    {
        $this->restaurantWithMenu();
        User::factory()->create(['name' => 'Asha Verma', 'phone' => '+91 98765 43210'])->assignRole('customer');

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');
        $this->say('1');
        $this->say('checkout');

        $this->assertStringContainsString('Where should we deliver', $this->reply());
        $this->assertStringNotContainsString('create a new account', $this->reply());
        $this->assertNull($this->lastOtp);                                           // no SMS code was ever sent

        // even typing "login" just confirms they are in
        $this->say('login');
        $this->assertStringContainsString('already logged in as Asha Verma', $this->reply());
        $this->assertNull($this->lastOtp);
    }

    public function test_when_a_number_has_a_customer_and_a_driver_account_the_customer_one_is_used(): void
    {
        Role::findOrCreate('delivery_partner', 'web');
        $driver = User::factory()->create(['name' => 'Driver Asha', 'phone' => '9876543210']);
        $driver->assignRole('delivery_partner');
        $customer = User::factory()->create(['name' => 'Asha Verma', 'phone' => '+919876543210']);
        $customer->assignRole('customer');

        $this->say('hi');
        $this->assertSame($customer->id, WhatsAppContact::where('phone', '+919876543210')->value('user_id'));
    }
}
