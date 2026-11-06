<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test of the whole online checkout for small baskets (around and below Rs 99), the way the app drives it:
 * create the Razorpay order -> the Razorpay SDK pays -> verify -> the order exists and is paid for exactly that amount.
 */
class RazorpayCheckoutSmallOrderSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rzp_secret';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('razorpay_key', 'rzp_test_key');
        AppSetting::setValue('razorpay_secret', self::SECRET);
        AppSetting::setValue('payment_gateway_enabled', '1');
        AppSetting::setValue('enabled_payment_gateways', json_encode(['razorpay']));
    }

    /** @return array<string, array{0: float}> */
    public static function basketPrices(): array
    {
        return [
            'Rs 1' => [1.0],
            'Rs 45.50' => [45.50],
            'Rs 89' => [89.0],
            'Rs 98.99' => [98.99],
            'Rs 99' => [99.0],
            'Rs 100' => [100.0],
        ];
    }

    #[DataProvider('basketPrices')]
    public function test_small_basket_pays_through_razorpay_and_places_the_order(float $price): void
    {
        [$user, $restaurant, $item, $address] = $this->world($price);
        Sanctum::actingAs($user);

        $sentMinor = null;
        Http::fake(function ($request) use (&$sentMinor) {
            if ($request->url() === 'https://api.razorpay.com/v1/orders' && $request->method() === 'POST') {
                $sentMinor = $request['amount'];

                return Http::response(['id' => 'order_SMOKE1', 'amount' => $request['amount'], 'currency' => 'INR']);
            }

            return Http::response([], 404);
        });

        $payload = [
            'restaurant_id' => $restaurant->id,
            'order_type' => 'delivery',
            'delivery_address_id' => $address->id,
            'payment_method' => 'razorpay',
            'items' => [['id' => $item->id, 'quantity' => 1]],
        ];

        $create = $this->postJson('/api/payments/checkout/create', $payload)->assertOk();
        $this->assertTrue($create->json('success'));
        $data = $create->json('data');

        // Razorpay must be asked for whole paise and the exact payable total.
        $this->assertIsInt($sentMinor);
        $this->assertSame($sentMinor, $data['amount_minor']);
        $this->assertSame('order_SMOKE1', $data['order_id']);
        $this->assertGreaterThan(0, $sentMinor);

        $paymentId = 'pay_SMOKE1';
        $verify = $this->postJson('/api/payments/checkout/verify', [
            'checkout_payment_token' => $data['checkout_payment_token'],
            'payment_method' => 'razorpay',
            'razorpay_order_id' => 'order_SMOKE1',
            'payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', 'order_SMOKE1|' . $paymentId, self::SECRET),
        ]);

        $verify->assertSuccessful();
        $this->assertTrue($verify->json('success'), $verify->getContent());

        $order = Order::where('customer_id', $user->id)->firstOrFail();
        $this->assertSame($sentMinor, (int) round(((float) $order->total) * 100), 'order total must equal what Razorpay charged');
        $this->assertSame('success', $order->payment_status);
    }

    public function test_sdk_error_after_a_real_charge_still_places_the_order_via_the_order_payments_check(): void
    {
        [$user, $restaurant, $item, $address] = $this->world(60.0);
        Sanctum::actingAs($user);

        $minor = null;
        Http::fake(function ($request) use (&$minor) {
            if ($request->url() === 'https://api.razorpay.com/v1/orders' && $request->method() === 'POST') {
                $minor = $request['amount'];

                return Http::response(['id' => 'order_SMOKE2', 'amount' => $minor, 'currency' => 'INR']);
            }
            if (str_contains($request->url(), '/orders/order_SMOKE2/payments')) {
                return Http::response(['items' => [['id' => 'pay_SMOKE2', 'status' => 'captured', 'amount' => $minor]]]);
            }

            return Http::response([], 404);
        });

        $data = $this->postJson('/api/payments/checkout/create', [
            'restaurant_id' => $restaurant->id,
            'order_type' => 'delivery',
            'delivery_address_id' => $address->id,
            'payment_method' => 'razorpay',
            'items' => [['id' => $item->id, 'quantity' => 1]],
        ])->assertOk()->json('data');

        // The SDK reported an error/cancel (no signature, no payment id): the server asks Razorpay itself.
        $verify = $this->postJson('/api/payments/checkout/verify', [
            'checkout_payment_token' => $data['checkout_payment_token'],
            'payment_method' => 'razorpay',
            'razorpay_order_id' => 'order_SMOKE2',
        ]);

        $this->assertTrue($verify->json('success'), $verify->getContent());
        $this->assertSame(1, Order::where('customer_id', $user->id)->count());
    }

    public function test_an_unpaid_razorpay_order_is_refused_and_creates_no_order(): void
    {
        [$user, $restaurant, $item, $address] = $this->world(60.0);
        Sanctum::actingAs($user);

        Http::fake(function ($request) {
            if ($request->url() === 'https://api.razorpay.com/v1/orders' && $request->method() === 'POST') {
                return Http::response(['id' => 'order_SMOKE3', 'amount' => $request['amount'], 'currency' => 'INR']);
            }

            return Http::response(['items' => []]);
        });

        $data = $this->postJson('/api/payments/checkout/create', [
            'restaurant_id' => $restaurant->id,
            'order_type' => 'delivery',
            'delivery_address_id' => $address->id,
            'payment_method' => 'razorpay',
            'items' => [['id' => $item->id, 'quantity' => 1]],
        ])->assertOk()->json('data');

        $this->postJson('/api/payments/checkout/verify', [
            'checkout_payment_token' => $data['checkout_payment_token'],
            'payment_method' => 'razorpay',
            'razorpay_order_id' => 'order_SMOKE3',
        ])->assertStatus(422);

        $this->assertSame(0, Order::where('customer_id', $user->id)->count());
    }

    /** @return array{0: User, 1: Restaurant, 2: MenuItem, 3: Address} */
    private function world(float $price): array
    {
        $user = User::factory()->create(['phone' => '9876543210']);
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Smoke Kitchen',
            'slug' => 'smoke-kitchen-' . uniqid(),
            'address' => 'Main Street',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'latitude' => 22.5726,
            'longitude' => 88.3639,
            'phone' => '9999999999',
            'email' => uniqid('smoke') . '@example.com',
            'cuisine' => ['Indian'],
            'is_open' => true,
            'weekly_timings' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['is_open' => true, 'open_time' => '00:00', 'close_time' => '23:59']])
                ->all(),
            'is_pure_veg' => false,
            'min_order_amount' => 0,
            'delivery_fee' => 20,
            'delivery_time' => 30,
            'rating' => 4.7,
            'total_ratings' => 10,
            'is_featured' => true,
            'is_verified' => true,
        ]);
        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Snack',
            'price' => $price,
            'is_available' => true,
            'is_veg' => true,
            'approval_status' => 'approved',
        ]);
        $address = Address::create([
            'user_id' => $user->id,
            'name' => 'Home',
            'address' => '1 Test Lane',
            'city' => 'Kolkata',
            'state' => 'WB',
            'pincode' => '700001',
            'phone' => '9999999999',
            'is_default' => true,
            'latitude' => 22.5726,
            'longitude' => 88.3639,
        ]);

        return [$user, $restaurant, $item, $address];
    }
}
