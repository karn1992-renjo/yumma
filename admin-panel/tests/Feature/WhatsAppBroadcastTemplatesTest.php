<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastTemplate;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppBroadcastVariables;
use App\Services\WhatsApp\WhatsAppTemplateRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppBroadcastTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'Hi {{1}}, this weekend only we have a special offer waiting for you! Use the code {{2}} at checkout to get a discount on your next order, and order from your favourite restaurants in just a few taps in the app. Thank you for being a valued customer of ours.';

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        foreach (['api_key' => 'K', 'app_name' => 'App', 'source_number' => '917834811114'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        Http::fake(['api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'gs-' . uniqid()])]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);
    }

    // ---- Rules ---------------------------------------------------------------------------------------

    public function test_the_rules_reject_what_gupshup_rejected_and_accept_good_text(): void
    {
        // the exact order_placed text Gupshup rejected for "too many variables for its length"
        $old = 'Hi {{1}}, your order #{{2}} from {{3}} is placed. Total {{4}}. Track it here: {{5}}.';
        $bad = WhatsAppTemplateRules::check($old);
        $this->assertNotEmpty($bad['errors']);
        $this->assertStringContainsString('Too many variables', implode(' ', $bad['errors']));

        $this->assertSame([], WhatsAppTemplateRules::check(self::GOOD)['errors']);

        $this->assertStringContainsString('start with a variable', implode(' ', WhatsAppTemplateRules::check('{{1}}, ' . self::GOOD)['errors']));
        $this->assertStringContainsString('end with a variable', implode(' ', WhatsAppTemplateRules::check(rtrim(self::GOOD, '.') . ' {{3}}.')['errors']));
        $this->assertStringContainsString('numbered in order', implode(' ', WhatsAppTemplateRules::check('Hi {{2}}, ' . substr(self::GOOD, 8))['errors']));
        $this->assertStringContainsString('next to each other', implode(' ', WhatsAppTemplateRules::check('Hi {{1}} {{2}}, ' . substr(self::GOOD, 8))['errors']));

        // every template the app itself uses must pass its own rules
        foreach (\App\Services\WhatsApp\WhatsAppTemplates::all() as $key => $tpl) {
            $this->assertSame([], WhatsAppTemplateRules::check($tpl['body'])['errors'], "built-in template {$key} breaks the rules");
        }
    }

    // ---- Creating templates ----------------------------------------------------------------------------

    public function test_page_lists_the_variables_and_creating_a_template_validates_and_saves(): void
    {
        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()
            ->assertSee('Variables you can use')->assertSee('{name}', false)->assertSee('{wallet_balance}', false)->assertSee('{gold_status}', false)
            ->assertSee('Create a broadcast template')->assertSee('Fixed text');

        // too short for its variables -> refused, nothing saved
        $this->post(route('admin.whatsapp.templates.store'), ['name' => 'Bad', 'body' => 'Hi {{1}}, code {{2}} now {{3}}.', 'var_type' => [1 => '{name}', 2 => 'fixed', 3 => 'fixed'], 'var_text' => [2 => 'X', 3 => 'Y']])
            ->assertSessionHasErrors('body');
        $this->assertSame(0, WhatsAppBroadcastTemplate::count());

        // a variable left without a meaning -> refused
        $this->post(route('admin.whatsapp.templates.store'), ['name' => 'Half', 'body' => self::GOOD, 'var_type' => [1 => '{name}', 2 => 'fixed'], 'var_text' => [2 => '']])
            ->assertSessionHasErrors('body');

        $this->post(route('admin.whatsapp.templates.store'), [
            'name' => 'Weekend Offer', 'body' => self::GOOD, 'language' => 'en',
            'var_type' => [1 => '{name}', 2 => 'fixed'], 'var_text' => [2 => 'WEEKEND50'],
        ])->assertSessionHas('success');

        $t = WhatsAppBroadcastTemplate::firstOrFail();
        $this->assertSame('weekend_offer', $t->template_name);
        $this->assertSame(['1' => '{name}', '2' => 'WEEKEND50'], $t->variables);
        $this->assertSame('draft', $t->status);
        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()->assertDontSee('Your templates');
    }

    public function test_a_template_must_have_its_provider_id_before_it_can_be_approved_or_used(): void
    {
        $t = WhatsAppBroadcastTemplate::create(['name' => 'Offer', 'template_name' => 'offer', 'body' => self::GOOD, 'variables' => ['1' => '{name}', '2' => 'WEEKEND50']]);
        WhatsAppContact::create(['phone' => '+919000000001', 'profile_name' => 'Ravi Kumar']);

        $this->post(route('admin.whatsapp.templates.update', $t), ['status' => 'approved', 'provider_template_id' => ''])->assertSessionHas('error');
        $this->assertSame('draft', $t->fresh()->status);

        $broadcast = fn () => $this->post(route('admin.whatsapp.broadcasts.store'), [
            'title' => 'Weekend', 'broadcast_template_id' => $t->id, 'audience' => 'contacts', 'consent' => '1',
        ]);
        $broadcast()->assertSessionHas('error');                                   // not approved yet
        $this->assertSame(0, WhatsAppBroadcast::count());

        $this->post(route('admin.whatsapp.templates.update', $t), ['status' => 'approved', 'provider_template_id' => 'c6aecef6-bcb0-4fb1-8100-28c094e3bc6b', 'notes' => ''])
            ->assertSessionHas('success');
        $broadcast()->assertSessionHas('success');

        $b = WhatsAppBroadcast::firstOrFail();
        $this->assertSame('c6aecef6-bcb0-4fb1-8100-28c094e3bc6b', $b->template);
        $this->assertSame(['{name}', 'WEEKEND50'], $b->params);
        $sent = WhatsAppMessage::where('event', 'broadcast')->firstOrFail();
        $this->assertSame(['Ravi', 'WEEKEND50'], $sent->payload['params']);        // {name} became this customer's first name
        Http::assertSent(fn ($r) => str_contains($r->url(), 'template/msg') && str_contains(urldecode($r->body()), '"id":"c6aecef6-bcb0-4fb1-8100-28c094e3bc6b"'));
    }

    // ---- Variables resolve per customer -------------------------------------------------------------------------

    public function test_each_customer_gets_their_own_values(): void
    {
        $asha = User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210', 'reward_points_balance' => 120]);
        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create()->id, 'name' => 'Spice Hub', 'slug' => 'sh-' . uniqid(), 'address' => 'x', 'city' => 'K', 'state' => 'WB', 'pincode' => '1',
            'latitude' => 22.5, 'longitude' => 88.3, 'phone' => '9', 'email' => uniqid() . '@e.com', 'cuisine' => ['a'], 'is_open' => true, 'is_pure_veg' => false,
            'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'rating' => 4, 'total_ratings' => 1, 'is_featured' => false, 'is_verified' => true,
        ]);
        foreach (['ORD1', 'ORD2'] as $n) {
            Order::withoutEvents(fn () => Order::create([
                'order_number' => $n, 'customer_id' => $asha->id, 'restaurant_id' => $restaurant->id, 'order_type' => 'delivery', 'items' => [], 'subtotal' => 1,
                'delivery_fee' => 0, 'platform_fee' => 0, 'tax' => 0, 'discount' => 0, 'total' => 1, 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'delivered',
                'customer_name' => 'Asha', 'customer_phone' => '9876543210', 'customer_address' => ['a' => 1], 'delivery_address' => 'x', 'delivery_lat' => 1, 'delivery_lng' => 1,
            ]));
        }

        $fill = fn (string $spec, string $phone, ?string $name = null) => WhatsAppBroadcastVariables::fill($spec, $phone, $name);

        $this->assertSame('Hi Asha, you have 2 orders and 120 points. Last: Spice Hub', $fill('Hi {name}, you have {orders_count} orders and {reward_points} points. Last: {last_order_restaurant}', '+919876543210'));
        $this->assertSame('Asha Verma', $fill('{full_name}', '+919876543210'));
        $this->assertSame('not active', $fill('{gold_status}', '+919876543210'));

        // someone we know nothing about gets safe defaults, never an empty or broken message
        $this->assertSame('Hi there, 0 orders', $fill('Hi {name}, {orders_count} orders', '+919999999999'));
        $this->assertSame('Hi Ravi', $fill('Hi {name}', '+919999999999', 'Ravi Kumar'));
        $this->assertSame('WEEKEND50', $fill('WEEKEND50', '+919999999999'));             // fixed text untouched

        // sample values for the live preview
        $this->assertSame('Hi Asha, 2 orders', WhatsAppBroadcastVariables::sampleFill('Hi {name}, 2 orders'));
    }

    public function test_templates_can_be_deleted(): void
    {
        $t = WhatsAppBroadcastTemplate::create(['name' => 'Old', 'template_name' => 'old', 'body' => self::GOOD, 'variables' => ['1' => '{name}', '2' => 'X']]);
        $this->delete(route('admin.whatsapp.templates.destroy', $t))->assertSessionHas('success');
        $this->assertSame(0, WhatsAppBroadcastTemplate::count());
    }
}
