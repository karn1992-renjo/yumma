<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppAdminToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x' . uniqid()]]])]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);
    }

    public function test_inbox_thread_agent_reply_and_hand_back(): void
    {
        $contact = WhatsAppContact::create(['phone' => '+919876543210', 'profile_name' => 'Asha', 'last_inbound_at' => now(), 'bot_state' => 'human']);
        WhatsAppMessage::create(['contact_id' => $contact->id, 'direction' => 'in', 'provider' => 'meta', 'phone' => $contact->phone, 'body' => 'my food was cold', 'status' => 'received']);

        $this->get(route('admin.whatsapp.inbox'))->assertOk()->assertSee('Asha')->assertSee('needs a person')->assertSee('my food was cold');
        $this->get(route('admin.whatsapp.thread', $contact))->assertOk()->assertSee('my food was cold')->assertSee('Reply');

        $this->post(route('admin.whatsapp.reply', $contact), ['text' => 'Sorry about that, refunding now.'])->assertSessionHas('success');
        $this->assertSame('Sorry about that, refunding now.', WhatsAppMessage::where('direction', 'out')->value('body'));
        $this->assertSame('human', $contact->fresh()->bot_state);

        $this->post(route('admin.whatsapp.hand-back', $contact))->assertSessionHas('success');
        $this->assertNull($contact->fresh()->bot_state);

        // outside the 24h window only a template is accepted
        $contact->update(['last_inbound_at' => now()->subDays(2)]);
        $this->get(route('admin.whatsapp.thread', $contact))->assertSee('Template identifier');
        $this->post(route('admin.whatsapp.reply', $contact), ['text' => 'late reply'])->assertSessionHas('error');
    }

    public function test_broadcast_audience_consent_opt_outs_and_batches(): void
    {
        WhatsAppContact::create(['phone' => '+919000000001', 'profile_name' => 'Ravi Kumar', 'opted_in' => true]);
        WhatsAppContact::create(['phone' => '+919000000002', 'profile_name' => 'Gone', 'opted_in' => false]);

        $this->post(route('admin.whatsapp.broadcasts.store'), ['title' => 'Offer', 'template' => 'weekend_offer', 'audience' => 'contacts'])
            ->assertSessionHasErrors('consent');

        $this->post(route('admin.whatsapp.broadcasts.store'), [
            'title' => 'Offer', 'template' => 'weekend_offer', 'params' => "{name}\nWEEKEND50", 'audience' => 'contacts', 'consent' => '1',
        ])->assertSessionHas('success');

        $b = WhatsAppBroadcast::firstOrFail();
        $this->assertSame(1, $b->total);                              // opted-out contact excluded
        $this->assertSame('done', $b->fresh()->status);
        $sent = WhatsAppMessage::where('event', 'broadcast')->firstOrFail();
        $this->assertSame('+919000000001', $sent->phone);
        $this->assertSame(['Ravi', 'WEEKEND50'], $sent->payload['params']);
        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()->assertSee('Offer')->assertSee('1 sent');
    }

    public function test_recent_customer_audience_and_cancel(): void
    {
        $user = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210']);
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => 'Spice Hub', 'slug' => 'sh-' . uniqid(), 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '1',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9', 'email' => uniqid() . '@e.com', 'cuisine' => ['a'], 'is_open' => true, 'is_pure_veg' => false,
            'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true,
        ]);
        Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD1', 'customer_id' => $user->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery', 'items' => [], 'subtotal' => 1,
            'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 1, 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'delivered',
            'customer_name' => 'Asha', 'customer_phone' => '9876543210', 'customer_address' => ['a' => 1], 'delivery_address' => 'x', 'delivery_lat' => 1, 'delivery_lng' => 1,
        ]));

        $broadcast = WhatsAppBroadcast::create(['title' => 'Win-back', 'template' => 'come_back', 'params' => ['{name}'], 'audience' => 'recent_customers', 'audience_days' => 30]);
        $this->assertSame(1, app(WhatsAppBroadcastService::class)->start($broadcast));

        app(WhatsAppBroadcastService::class)->cancel($broadcast->fresh());
        $this->assertSame('cancelled', $broadcast->fresh()->status);
        $this->assertSame(0, app(WhatsAppBroadcastService::class)->runBatch());
        $this->assertSame(0, WhatsAppMessage::where('event', 'broadcast')->count());
    }

    public function test_reports_page_and_command(): void
    {
        WhatsAppMessage::create(['direction' => 'out', 'provider' => 'meta', 'phone' => '+91900', 'event' => 'order_placed', 'status' => 'read']);
        WhatsAppMessage::create(['direction' => 'out', 'provider' => 'meta', 'phone' => '+91901', 'event' => 'order_placed', 'status' => 'failed', 'error' => 'Template does not exist']);
        WhatsAppMessage::create(['direction' => 'in', 'provider' => 'meta', 'phone' => '+91900', 'status' => 'received', 'body' => 'hi']);

        $this->get(route('admin.whatsapp.reports'))->assertOk()->assertSee('order placed')->assertSee('Template does not exist');
        $this->artisan('whatsapp:broadcasts')->assertSuccessful();
    }
}
