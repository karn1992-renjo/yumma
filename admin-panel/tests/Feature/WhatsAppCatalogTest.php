<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppCatalogItem;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppCatalogSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The native WhatsApp catalog and cart: sync, show, receive the cart, order. */
class WhatsAppCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const COVER = 'https://cdn.example.com/spice-hub.jpg';

    private Restaurant $restaurant;
    private MenuItem $paneer;
    private MenuItem $naan;
    private bool $batchFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(13, 0));   // restaurants are open at midday whatever time the suite runs
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v', 'catalog_id' => 'CAT123'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        AppSetting::setValue('whatsapp_catalog_enabled', '1');
        Role::findOrCreate('customer', 'web');

        Http::fake([
            'graph.facebook.com/*/items_batch' => fn () => $this->batchFails
                ? Http::response(['error' => ['message' => 'Catalog not connected to this WhatsApp account']], 400)
                : Http::response(['handles' => ['h1']]),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]]),
        ]);

        $this->restaurant = Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9222222222'])->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'Main Street', 'city' => 'Kolkata',
            'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['Indian'],
            'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'delivery_radius' => 8, 'rating' => 4.5,
            'total_ratings' => 3, 'is_featured' => false, 'is_verified' => true, 'cover_image' => self::COVER,
        ]);
        $starters = Category::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Starters', 'is_active' => true]);
        $breads = Category::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Breads', 'is_active' => true]);
        $this->paneer = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'category_id' => $starters->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true,
            'is_veg' => true, 'is_bestseller' => true, 'description' => 'Smoky cottage cheese.', 'images' => ['https://cdn.example.com/paneer.jpg']]);
        $this->naan = MenuItem::create(['restaurant_id' => $this->restaurant->id, 'category_id' => $breads->id, 'name' => 'Butter Naan', 'price' => 45, 'is_available' => true, 'is_veg' => true]);   // no photo: restaurant cover is used
        // not sold from a WhatsApp cart:
        MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Margherita Pizza', 'price' => 250, 'is_available' => true, 'variants' => [['id' => 'm', 'name' => 'Medium', 'price' => 0]], 'images' => ['https://cdn.example.com/p.jpg']]);
        MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Sold Out Soup', 'price' => 90, 'is_available' => false, 'images' => ['https://cdn.example.com/s.jpg']]);
        MenuItem::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Free Sample', 'price' => 0, 'is_available' => true, 'images' => ['https://cdn.example.com/f.jpg']]);
    }

    private function batches(): array
    {
        return Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'items_batch'))
            ->map(fn ($pair) => json_decode($pair[0]['requests'], true))->values()->all();
    }

    // ---- Sync ----------------------------------------------------------------------------------------------------

    public function test_only_sellable_dishes_are_synced_with_the_right_fields(): void
    {
        $result = app(WhatsAppCatalogSync::class)->run();

        $this->assertSame(['created' => 2, 'updated' => 0, 'removed' => 0, 'failed' => 0, 'total' => 2], array_intersect_key($result, array_flip(['created', 'updated', 'removed', 'failed', 'total'])));
        $requests = collect($this->batches()[0]);
        $this->assertSame(['mi_' . $this->paneer->id, 'mi_' . $this->naan->id], $requests->pluck('data.id')->sort()->values()->all());
        $this->assertSame(['UPDATE'], $requests->pluck('method')->unique()->all());

        $p = $requests->firstWhere('data.id', 'mi_' . $this->paneer->id)['data'];
        $this->assertSame('Paneer Tikka', $p['title']);
        $this->assertSame('220.00 INR', $p['price']);
        $this->assertSame('in stock', $p['availability']);
        $this->assertSame('https://cdn.example.com/paneer.jpg', $p['image_link']);
        $this->assertSame('Spice Hub', $p['brand']);
        $this->assertSame('Starters', $p['product_type']);
        $this->assertSame('r' . $this->restaurant->id, $p['custom_label_0']);
        $this->assertStringContainsString('/restaurants/spice-hub', $p['link']);

        $n = $requests->firstWhere('data.id', 'mi_' . $this->naan->id)['data'];
        $this->assertSame(self::COVER, $n['image_link']);                              // no dish photo: the restaurant's picture
        $this->assertSame('Butter Naan from Spice Hub', $n['description']);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com') && str_contains($r->url(), '/CAT123/items_batch') && $r->hasHeader('Authorization', 'Bearer t'));
        $this->assertSame(2, WhatsAppCatalogItem::where('status', 'synced')->count());
    }

    public function test_a_second_sync_sends_only_what_changed_and_removes_what_is_gone(): void
    {
        $sync = app(WhatsAppCatalogSync::class);
        $sync->run();
        $before = count($this->batches());

        $again = $sync->run();
        $this->assertSame(0, $again['created'] + $again['updated'] + $again['removed']);
        $this->assertCount($before, $this->batches());                                  // nothing was sent

        $this->paneer->update(['price' => 240]);
        $changed = $sync->run();
        $this->assertSame(1, $changed['updated']);
        $this->assertSame('240.00 INR', collect(last($this->batches()))->first()['data']['price']);

        $this->restaurant->update(['is_open' => false]);                                // closed: shown as out of stock
        $sync->run();
        $this->assertSame('out of stock', collect(last($this->batches()))->first()['data']['availability']);

        $this->restaurant->update(['is_open' => true]);
        $this->naan->update(['is_available' => false]);                                 // no longer sold: removed
        $removed = $sync->run();
        $this->assertSame(1, $removed['removed']);
        $this->assertContains(['method' => 'DELETE', 'data' => ['id' => 'mi_' . $this->naan->id]], collect($this->batches())->flatten(1)->all());
        $this->assertSame(0, WhatsAppCatalogItem::where('menu_item_id', $this->naan->id)->count());
    }

    public function test_a_failed_sync_is_recorded_and_retried(): void
    {
        $this->batchFails = true;

        $result = app(WhatsAppCatalogSync::class)->run();
        $this->assertSame(2, $result['failed']);
        $this->assertStringContainsString('Catalog not connected', $result['error']);
        $this->assertSame(2, WhatsAppCatalogItem::where('status', 'failed')->count());

        $this->batchFails = false;
        $retry = app(WhatsAppCatalogSync::class)->run();
        $this->assertSame(0, $retry['failed']);
        $this->assertSame(2, WhatsAppCatalogItem::where('status', 'synced')->count());
    }

    public function test_nothing_syncs_unless_the_catalog_is_switched_on_and_set_up(): void
    {
        AppSetting::setValue('whatsapp_catalog_enabled', '0');
        $r = app(WhatsAppCatalogSync::class)->run();
        $this->assertStringContainsString('Turn on the catalog', $r['error']);
        $this->assertSame([], $this->batches());
        $this->artisan('whatsapp:catalog-sync')->assertSuccessful();
    }

    // ---- In the chat ----------------------------------------------------------------------------------------------

    private function customer(): User
    {
        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $user->assignRole('customer');

        return $user;
    }

    private function webhook(array $message): void
    {
        $message += ['id' => 'wamid.' . uniqid(), 'from' => '919876543210'];
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Asha']]], 'messages' => [$message],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    private function say(string $text): void
    {
        $this->webhook(['type' => 'text', 'text' => ['body' => $text]]);
    }

    private function reply(): string
    {
        return (string) WhatsAppMessage::where('direction', 'out')->where('event', 'bot')->where('type', '!=', 'image')->latest('id')->value('body');
    }

    private function sendCart(array $lines, ?string $note = null): void
    {
        $this->webhook(['type' => 'order', 'order' => ['catalog_id' => 'CAT123', 'text' => $note, 'product_items' => $lines]]);
    }

    public function test_picking_a_restaurant_shows_its_catalog_with_a_view_items_button(): void
    {
        $this->customer();
        app(WhatsAppCatalogSync::class)->run();

        $this->say('3');
        $this->webhook(['type' => 'location', 'location' => ['latitude' => 22.573, 'longitude' => 88.364]]);
        $this->say('1');

        Http::assertSent(function (HttpRequest $r) {
            if (($r['type'] ?? null) !== 'interactive' || ($r['interactive']['type'] ?? null) !== 'product_list') {
                return false;
            }
            $sections = collect($r['interactive']['action']['sections']);

            return $r['interactive']['action']['catalog_id'] === 'CAT123'
                && $r['interactive']['header']['text'] === 'Spice Hub'
                && str_contains($r['interactive']['body']['text'], 'View items')
                && $sections->pluck('title')->sort()->values()->all() === ['Breads', 'Starters']
                && $sections->flatMap(fn ($s) => collect($s['product_items'])->pluck('product_retailer_id'))->sort()->values()->all()
                    === ['mi_' . $this->paneer->id, 'mi_' . $this->naan->id];
        });
        $this->assertStringContainsString('TEXT MENU', $this->reply());
        $this->assertStringNotContainsString('To add: reply the item number', $this->reply());       // the numbered menu is only on request

        $this->say('text menu');
        $this->assertStringContainsString('To add: reply the item number', $this->reply());
        $this->assertStringContainsString('Paneer Tikka', $this->reply());
    }

    public function test_without_a_synced_catalog_the_text_menu_is_used(): void
    {
        $this->customer();                                                              // nothing synced yet

        $this->say('3');
        $this->webhook(['type' => 'location', 'location' => ['latitude' => 22.573, 'longitude' => 88.364]]);
        $this->say('1');

        $this->assertStringContainsString('To add: reply the item number', $this->reply());
        Http::assertNotSent(fn (HttpRequest $r) => ($r['interactive']['type'] ?? null) === 'product_list');
    }

    public function test_a_cart_sent_from_the_catalog_becomes_a_checkout_and_an_order(): void
    {
        $user = $this->customer();
        app(WhatsAppCatalogSync::class)->run();

        // the cart arrives with a forged price: the order must use OUR price
        $this->sendCart([
            ['product_retailer_id' => 'mi_' . $this->paneer->id, 'quantity' => 2, 'item_price' => 1, 'currency' => 'INR'],
            ['product_retailer_id' => 'mi_' . $this->naan->id, 'quantity' => 3, 'item_price' => 1, 'currency' => 'INR'],
        ], 'Less spicy please');

        $reply = (string) WhatsAppMessage::where('event', 'bot')->where('body', 'like', '%Got your cart%')->latest('id')->value('body');
        $this->assertStringContainsString('2 × Paneer Tikka – ', $reply);
        $this->assertStringContainsString('3 × Butter Naan', $reply);
        $this->assertStringContainsString('Items total: ', $reply);
        $this->assertSame(0, Order::count());                                           // a cart is not an order yet
        $this->assertStringContainsString('Where should we deliver', $this->reply());      // checkout starts straight away
        $this->say('Flat 4B, Lake View Apartments, near City Mall');
        $this->webhook(['type' => 'location', 'location' => ['latitude' => 22.581, 'longitude' => 88.371]]);
        $this->say('skip');
        $this->say('1');
        $confirm = $this->reply();
        $this->assertStringContainsString('Please confirm your order', $confirm);
        $this->say('yes');

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->customer_id);
        $this->assertSame(5, collect($order->items)->sum('quantity'));                  // 2 + 3
        $this->assertGreaterThanOrEqual(2 * 220 + 3 * 45, (float) $order->total);       // 575 from our prices, not "1"
        $this->assertStringContainsString('Less spicy please', (string) $order->special_instructions);
        $this->assertStringContainsString('Order placed', $this->reply());
    }

    public function test_bad_carts_are_explained(): void
    {
        $this->customer();
        app(WhatsAppCatalogSync::class)->run();

        $other = Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9333333333'])->id, 'name' => 'Other Kitchen', 'slug' => 'other-kitchen', 'address' => 'x', 'city' => 'K', 'state' => 'WB',
            'pincode' => '1', 'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9', 'email' => 'o@example.com', 'cuisine' => ['a'], 'is_open' => true, 'is_pure_veg' => false,
            'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true, 'cover_image' => self::COVER,
        ]);
        $dish = MenuItem::create(['restaurant_id' => $other->id, 'name' => 'Dal', 'price' => 100, 'is_available' => true]);

        $this->sendCart([['product_retailer_id' => 'mi_' . $this->paneer->id, 'quantity' => 1], ['product_retailer_id' => 'mi_' . $dish->id, 'quantity' => 1]]);
        $this->assertStringContainsString('more than one restaurant', $this->reply());

        $this->sendCart([['product_retailer_id' => 'nonsense', 'quantity' => 1], ['product_retailer_id' => 'mi_99999', 'quantity' => 1]]);
        $this->assertStringContainsString("couldn't use that cart", $this->reply());

        $this->paneer->update(['is_available' => false]);                               // sold out between sync and checkout
        $this->sendCart([['product_retailer_id' => 'mi_' . $this->paneer->id, 'quantity' => 1], ['product_retailer_id' => 'mi_' . $this->naan->id, 'quantity' => 1]]);
        $this->assertStringContainsString('Skipped 1 dish', WhatsAppMessage::where('event', 'bot')->where('body', 'like', '%Got your cart%')->latest('id')->value('body'));
        $this->assertSame(0, Order::count());
    }

    public function test_address_step_offers_detect_saved_and_type_buttons(): void
    {
        $user = $this->customer();
        \App\Models\Address::create(['user_id' => $user->id, 'name' => 'Home', 'address' => '12 Lake Road', 'city' => 'Kolkata', 'state' => 'WB', 'pincode' => '700001', 'phone' => '9876543210', 'is_default' => true]);
        app(WhatsAppCatalogSync::class)->run();

        $this->sendCart([['product_retailer_id' => 'mi_' . $this->naan->id, 'quantity' => 1]]);
        Http::assertSent(fn (HttpRequest $r) => ($r['interactive']['type'] ?? null) === 'button'
            && collect($r['interactive']['action']['buttons'])->pluck('reply.id')->all() === ['say:detect', 'say:saved', 'say:type']);

        $this->webhook(['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'say:detect', 'title' => '📍 Detect my location']]]);
        Http::assertSent(fn (HttpRequest $r) => ($r['interactive']['type'] ?? null) === 'location_request_message');

        $this->webhook(['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'say:saved', 'title' => '🏠 Saved address']]]);
        Http::assertSent(fn (HttpRequest $r) => ($r['interactive']['type'] ?? null) === 'list'
            && $r['interactive']['action']['sections'][0]['rows'][0]['id'] === 'say:1' && $r['interactive']['action']['sections'][0]['rows'][0]['title'] === 'Home');

        $this->webhook(['type' => 'interactive', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'say:1', 'title' => 'Home']]]);
        $this->assertStringContainsString('location pin', $this->reply());              // saved address chosen, pin still needed
    }

    // ---- Admin ------------------------------------------------------------------------------------------------------

    public function test_admin_can_see_sync_and_switch_on_the_cart(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.whatsapp.settings'))->assertOk()->assertSee('In-chat catalog')->assertSee('Sync menu now')->assertSee('Catalog ID (optional)');

        $this->post(route('admin.whatsapp.catalog.sync'))->assertSessionHas('success');
        $this->get(route('admin.whatsapp.settings'))->assertSee('2 dishes in the catalog')->assertSee('2</b> added', false);

        Http::fake(['graph.facebook.com/*/whatsapp_commerce_settings' => Http::response(['success' => true])]);
        $this->post(route('admin.whatsapp.catalog.cart'))->assertSessionHas('success');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/1/whatsapp_commerce_settings') && $r['is_cart_enabled'] === true && $r['is_catalog_visible'] === true);
    }
}
