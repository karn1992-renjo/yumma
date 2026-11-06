<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $provider, array $fields): WhatsAppService
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', $provider);
        foreach ($fields as $k => $v) {
            AppSetting::setValue("whatsapp_{$provider}_{$k}", $v);
        }

        return app(WhatsAppService::class);
    }

    private function openWindow(string $phone): void
    {
        WhatsAppContact::create(['phone' => $phone, 'last_inbound_at' => now()]);
    }

    // ---- Sending, per provider ---------------------------------------------------------------

    public function test_meta_sends_text_and_template_with_the_right_payload(): void
    {
        $svc = $this->configure('meta', ['phone_number_id' => '1234', 'access_token' => 'tok', 'app_secret' => 's', 'verify_token' => 'v']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->openWindow('+919876543210');

        $text = $svc->sendText('9876543210', 'Hello');
        $this->assertSame('sent', $text->status);
        $this->assertSame('wamid.1', $text->provider_message_id);

        $template = $svc->sendTemplate('9876543210', 'order_placed', ['Asha', '1001', 'Spice Hub', '₹250', 'https://x/t']);
        $this->assertSame('sent', $template->status);
        $this->assertStringContainsString('Spice Hub', $template->body);

        Http::assertSent(function (HttpRequest $r) {
            return str_contains($r->url(), '/1234/messages') && $r->hasHeader('Authorization', 'Bearer tok')
                && $r['type'] === 'text' && $r['to'] === '919876543210' && $r['text']['body'] === 'Hello';
        });
        Http::assertSent(fn (HttpRequest $r) => ($r['type'] ?? null) === 'template' && $r['template']['name'] === 'order_placed'
            && $r['template']['components'][0]['parameters'][2]['text'] === 'Spice Hub');
    }

    public function test_twilio_gupshup_aisensy_interakt_build_their_own_requests(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM1']),
            'api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'gs-1']),
            'backend.aisensy.com/*' => Http::response(['submitted_message_id' => 'ai-1']),
            'api.interakt.ai/*' => Http::response(['result' => true, 'id' => 'ik-1']),
        ]);

        $tw = $this->configure('twilio', ['account_sid' => 'AC1', 'auth_token' => 'secret', 'from_number' => '+14155238886']);
        $this->assertSame('SM1', $tw->sendRawTemplate('9876543210', 'HXabc', ['a', 'b'])->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'Accounts/AC1/Messages.json')
            && str_contains($r->body(), 'whatsapp%3A%2B919876543210') && str_contains($r->body(), 'ContentSid=HXabc'));

        $gs = $this->configure('gupshup', ['api_key' => 'k', 'app_name' => 'App', 'source_number' => '919999999999']);
        $this->assertSame('gs-1', $gs->sendRawTemplate('9876543210', 'tpl-uuid', ['x'])->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'template/msg') && $r->hasHeader('apikey', 'k') && str_contains($r->body(), 'destination=919876543210'));

        $ai = $this->configure('aisensy', ['api_key' => 'k', 'user_name' => 'Biz']);
        $this->assertSame('ai-1', $ai->sendRawTemplate('9876543210', 'order_campaign', ['x'])->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'aisensy.com') && $r['campaignName'] === 'order_campaign' && $r['destination'] === '919876543210');

        $ik = $this->configure('interakt', ['api_key' => 'abc', 'default_country_code' => '+91']);
        $this->assertSame('ik-1', $ik->sendRawTemplate('9876543210', 'order_placed', ['x'], 'en')->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'interakt.ai') && $r->hasHeader('Authorization', 'Basic abc')
            && $r['countryCode'] === '+91' && $r['phoneNumber'] === '9876543210' && $r['template']['name'] === 'order_placed');
    }

    public function test_free_text_is_refused_outside_the_24_hour_window_and_for_template_only_providers(): void
    {
        $svc = $this->configure('meta', ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v']);
        Http::fake();

        $closed = $svc->sendText('9876543210', 'Hi');
        $this->assertSame('failed', $closed->status);
        $this->assertStringContainsString('24-hour', $closed->error);
        Http::assertNothingSent();

        $ai = $this->configure('aisensy', ['api_key' => 'k', 'user_name' => 'Biz']);
        $this->openWindow('+919811111111');
        $this->assertStringContainsString('only send templates', $ai->sendText('9811111111', 'Hi')->error);
    }

    public function test_nothing_is_sent_when_disabled_or_unconfigured_or_opted_out(): void
    {
        Http::fake();
        $svc = app(WhatsAppService::class);
        $this->assertSame('failed', $svc->sendTemplate('9876543210', 'order_placed', [])->status);   // disabled

        $svc = $this->configure('meta', ['phone_number_id' => '1']);                                  // missing token etc.
        $this->assertSame('failed', $svc->sendTemplate('9876543210', 'order_placed', [])->status);

        $svc = $this->configure('meta', ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v']);
        WhatsAppContact::create(['phone' => '+919876543210', 'opted_in' => false]);
        $this->assertStringContainsString('opted out', $svc->sendTemplate('9876543210', 'order_placed', [])->error);
        Http::assertNothingSent();
    }

    public function test_provider_errors_are_recorded_not_thrown(): void
    {
        $svc = $this->configure('meta', ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template does not exist']], 400)]);

        $m = $svc->sendTemplate('9876543210', 'order_placed', []);
        $this->assertSame('failed', $m->status);
        $this->assertStringContainsString('Template does not exist', $m->error);
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $m->id, 'status' => 'failed']);
    }

    // ---- Webhooks ------------------------------------------------------------------------------

    public function test_meta_webhook_handshake_signature_inbound_status_and_opt_out(): void
    {
        $svc = $this->configure('meta', ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'myverify']);
        Event::fake([\App\Events\WhatsAppMessageReceived::class]);

        $this->get('/api/webhooks/whatsapp/meta?hub_mode=subscribe&hub_verify_token=myverify&hub_challenge=CHALLENGE')
            ->assertOk()->assertSee('CHALLENGE');
        $this->get('/api/webhooks/whatsapp/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=X')->assertForbidden();

        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Asha']]],
            'messages' => [['id' => 'wamid.in1', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'Track my order']]],
        ]]]]]]);
        $sig = 'sha256=' . hash_hmac('sha256', $body, 'appsecret');

        // bad signature rejected
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertForbidden();

        $ok = fn () => $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig, 'CONTENT_TYPE' => 'application/json'], $body);
        $ok()->assertOk()->assertJsonPath('messages', 1);
        $ok()->assertOk()->assertJsonPath('messages', 0);          // duplicate delivery ignored

        $contact = WhatsAppContact::where('phone', '+919876543210')->firstOrFail();
        $this->assertSame('Asha', $contact->profile_name);
        $this->assertTrue($contact->inServiceWindow());
        $this->assertSame(1, WhatsAppMessage::where('direction', 'in')->count());
        Event::assertDispatched(\App\Events\WhatsAppMessageReceived::class);

        // delivery receipts move forward, never backwards
        $out = WhatsAppMessage::create(['direction' => 'out', 'provider' => 'meta', 'phone' => '+919876543210', 'provider_message_id' => 'wamid.out1', 'status' => 'sent']);
        $status = fn (string $s) => json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.out1', 'status' => $s]]]]]]]]);
        foreach (['read', 'delivered'] as $s) {
            $b = $status($s);
            $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $b, 'appsecret'), 'CONTENT_TYPE' => 'application/json'], $b)->assertOk();
        }
        $this->assertSame('read', $out->fresh()->status);

        // STOP opts the customer out; START opts back in
        $stop = json_encode(['entry' => [['changes' => [['value' => ['messages' => [['id' => 'w.stop', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $stop, 'appsecret'), 'CONTENT_TYPE' => 'application/json'], $stop)->assertOk();
        $this->assertFalse($contact->fresh()->opted_in);
    }

    public function test_unsigned_providers_need_the_secret_key_and_only_the_active_provider_answers(): void
    {
        $svc = $this->configure('gupshup', ['api_key' => 'k', 'app_name' => 'App', 'source_number' => '919999999999']);
        $payload = ['type' => 'message', 'timestamp' => 1700000000000, 'payload' => ['id' => 'g1', 'source' => '919876543210', 'type' => 'text', 'payload' => ['text' => 'hello'], 'sender' => ['phone' => '919876543210', 'name' => 'Ravi']]];

        $this->postJson('/api/webhooks/whatsapp/gupshup', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/whatsapp/gupshup?key=' . $svc->webhookToken(), $payload)->assertOk()->assertJsonPath('messages', 1);
        $this->postJson('/api/webhooks/whatsapp/meta', [])->assertNotFound();                 // not the selected provider
        $this->assertSame('Ravi', WhatsAppContact::where('phone', '+919876543210')->value('profile_name'));
    }

    public function test_twilio_signature_is_verified_and_status_callbacks_update_the_log(): void
    {
        $this->configure('twilio', ['account_sid' => 'AC1', 'auth_token' => 'tw-token', 'from_number' => '+14155238886']);
        WhatsAppMessage::create(['direction' => 'out', 'provider' => 'twilio', 'phone' => '+919876543210', 'provider_message_id' => 'SM9', 'status' => 'sent']);

        $url = url('/api/webhooks/whatsapp/twilio');
        $params = ['MessageSid' => 'SM9', 'MessageStatus' => 'delivered'];
        ksort($params);
        $data = $url . 'MessageSid' . 'SM9' . 'MessageStatus' . 'delivered';
        $good = base64_encode(hash_hmac('sha1', $data, 'tw-token', true));

        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => 'nope'])->assertForbidden();
        $this->post('/api/webhooks/whatsapp/twilio', $params, ['X-Twilio-Signature' => $good])->assertOk()->assertJsonPath('statuses', 1);
        $this->assertSame('delivered', WhatsAppMessage::where('provider_message_id', 'SM9')->value('status'));
    }

    // ---- Admin ----------------------------------------------------------------------------------------

    public function test_admin_can_open_save_and_test_the_dynamic_form(): void
    {
        $role = Role::findOrCreate('franchise_ops', 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage_franchises', 'web'));
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);

        $this->get(route('admin.whatsapp.settings'))->assertOk()
            ->assertSee('Meta WhatsApp Cloud API')->assertSee('Twilio')->assertSee('Gupshup')->assertSee('AiSensy')->assertSee('Interakt')
            ->assertSee('Phone number ID')->assertSee('Content SID', false);

        $this->post(route('admin.whatsapp.save'), [
            'whatsapp_enabled' => '1', 'whatsapp_provider' => 'interakt', 'whatsapp_template_language' => 'en',
            'cfg' => ['interakt' => ['api_key' => 'KEY123', 'default_country_code' => '+91']],
            'tpl' => ['interakt' => ['order_placed' => 'swado_order_placed']],
            'events' => ['order_placed' => '1'],
        ])->assertSessionHas('success');

        $this->assertSame('interakt', AppSetting::getValue('whatsapp_provider'));
        $this->assertSame('KEY123', AppSetting::getValue('whatsapp_interakt_api_key'));
        $this->assertSame('swado_order_placed', AppSetting::getValue('whatsapp_tpl_interakt_order_placed'));
        $this->assertSame('0', AppSetting::getValue('whatsapp_event_order_cancelled'));

        // a blank secret keeps the saved value
        $this->post(route('admin.whatsapp.save'), ['whatsapp_enabled' => '1', 'whatsapp_provider' => 'interakt', 'cfg' => ['interakt' => ['api_key' => '']]]);
        $this->assertSame('KEY123', AppSetting::getValue('whatsapp_interakt_api_key'));

        Http::fake(['api.interakt.ai/*' => Http::response(['result' => true, 'id' => 'ik-9'])]);
        $this->post(route('admin.whatsapp.test'), ['phone' => '9876543210', 'template' => 'hello_world'])->assertSessionHas('success');
        $this->get(route('admin.whatsapp.messages'))->assertOk()->assertSee('hello_world');
    }
}
