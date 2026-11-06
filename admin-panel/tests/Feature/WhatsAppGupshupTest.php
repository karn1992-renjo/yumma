<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Gupshup, checked against the payload and request formats in Gupshup's published docs. */
class WhatsAppGupshupTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        foreach (['api_key' => 'GS-KEY', 'app_name' => 'SwadoApp', 'source_number' => '917834811114'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        $this->svc = app(WhatsAppService::class);
    }

    private function hook(array $payload, bool $viaPath = true)
    {
        $url = '/api/webhooks/whatsapp/gupshup' . ($viaPath ? '/' . $this->svc->webhookToken() : '?key=' . $this->svc->webhookToken());

        return $this->postJson($url, $payload);
    }

    public function test_template_and_text_requests_match_the_gupshup_docs(): void
    {
        Http::fake(['api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'ee4a68a0-1203-4c85-8dc3-49d0b3226a35'])]);
        AppSetting::setValue('whatsapp_tpl_gupshup_order_placed', 'c6aecef6-bcb0-4fb1-8100-28c094e3bc6b');

        $m = $this->svc->sendTemplate('9876543210', 'order_placed', ['Asha', 'ORD1', 'Spice Hub', '₹250', 'https://x/t']);
        $this->assertSame('sent', $m->status);
        $this->assertSame('ee4a68a0-1203-4c85-8dc3-49d0b3226a35', $m->provider_message_id);

        Http::assertSent(function (HttpRequest $r) {
            $body = urldecode($r->body());

            return $r->url() === 'https://api.gupshup.io/wa/api/v1/template/msg' && $r->hasHeader('apikey', 'GS-KEY')
                && str_contains($body, 'source=917834811114') && str_contains($body, 'destination=919876543210')
                && str_contains($body, '"id":"c6aecef6-bcb0-4fb1-8100-28c094e3bc6b"') && str_contains($body, '"params":["Asha","ORD1","Spice Hub"');
        });

        WhatsAppContact::updateOrCreate(['phone' => '+919876543210'], ['last_inbound_at' => now()]);
        $this->assertSame('sent', $this->svc->sendText('9876543210', 'Hello from Gupshup')->status);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.gupshup.io/wa/api/v1/msg'
            && str_contains(urldecode($r->body()), 'message={"type":"text","text":"Hello from Gupshup"}')
            && str_contains($r->body(), 'src.name=SwadoApp') && str_contains($r->body(), 'channel=whatsapp'));
    }

    public function test_gupshup_error_text_is_recorded_on_the_message(): void
    {
        Http::fake(['api.gupshup.io/*' => Http::response(['message' => 'Invalid App Details', 'status' => 'error'], 400)]);

        $m = $this->svc->sendRawTemplate('9876543210', 'some-template-id', ['x']);
        $this->assertSame('failed', $m->status);
        $this->assertStringContainsString('Invalid App Details', $m->error);
    }

    public function test_inbound_text_and_location_from_the_docs_are_understood(): void
    {
        $this->hook([
            'app' => 'DemoApp', 'timestamp' => 1580227766370, 'version' => 2, 'type' => 'message',
            'payload' => ['id' => 'ABEGkYaYVSEEAhAL3SLAWwHKeKrt6s3FKB0c', 'source' => '919876543210', 'type' => 'text', 'payload' => ['text' => 'Hi'],
                'sender' => ['phone' => '919876543210', 'name' => 'Andy', 'country_code' => '91', 'dial_code' => '9876543210']],
        ])->assertOk()->assertJsonPath('messages', 1);

        $contact = WhatsAppContact::where('phone', '+919876543210')->firstOrFail();
        $this->assertSame('Andy', $contact->profile_name);
        $this->assertSame('Hi', WhatsAppMessage::where('direction', 'in')->value('body'));
        $this->assertTrue($contact->inServiceWindow());

        $this->hook([
            'app' => 'DemoApp', 'timestamp' => 1580227766371, 'version' => 2, 'type' => 'message',
            'payload' => ['id' => 'LOC1', 'source' => '919876543210', 'type' => 'location', 'payload' => ['longitude' => '72.8552172', 'latitude' => '19.1453658'],
                'sender' => ['phone' => '919876543210', 'name' => 'Andy']],
        ])->assertOk();
        $loc = WhatsAppMessage::where('provider_message_id', 'LOC1')->firstOrFail();
        $this->assertSame('location', $loc->type);
        $this->assertSame('19.1453658', json_decode($loc->payload['payload'], true)['latitude']);
    }

    public function test_delivery_events_use_the_right_ids_and_never_go_backwards(): void
    {
        // our row stores the Gupshup message id returned when sending
        $out = WhatsAppMessage::create(['direction' => 'out', 'provider' => 'gupshup', 'phone' => '+919876543210', 'status' => 'sent',
            'provider_message_id' => 'ee4a68a0-1203-4c85-8dc3-49d0b3226a35']);

        // read first (id = WhatsApp id, gsId = Gupshup id), then a late "delivered": must stay read
        foreach (['read', 'delivered'] as $event) {
            $this->hook(['app' => 'DemoApp', 'timestamp' => 1585344602933, 'version' => 2, 'type' => 'message-event',
                'payload' => ['id' => 'gBEGkYaYVSEEAgnZxQ3JmKK6Wvg', 'gsId' => 'ee4a68a0-1203-4c85-8dc3-49d0b3226a35', 'type' => $event,
                    'destination' => '919876543210', 'payload' => ['ts' => 1585344602]]])->assertOk();
        }
        $this->assertSame('read', $out->fresh()->status);

        // failure that Gupshup reports on its own id, with its real reason text
        $failed = WhatsAppMessage::create(['direction' => 'out', 'provider' => 'gupshup', 'phone' => '+919876543210', 'status' => 'sent',
            'provider_message_id' => '9163a016-710e-41ee-978b-79a1adbd734e']);
        $this->hook(['app' => 'DemoApp', 'timestamp' => 1663138637856, 'version' => 2, 'type' => 'message-event',
            'payload' => ['id' => '9163a016-710e-41ee-978b-79a1adbd734e', 'gsId' => '72f61f22-5aa4-4615-a970-943edf6da01c', 'type' => 'failed',
                'destination' => '919876543210', 'payload' => ['code' => 470, 'reason' => 'Message failed to send because more than 24 hours have passed since the customer last replied to this number']]])->assertOk();
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertStringContainsString('more than 24 hours', $failed->fresh()->error);
    }

    public function test_the_secret_key_works_in_the_path_or_the_query_but_not_wrong_or_missing(): void
    {
        $payload = ['app' => 'A', 'timestamp' => 1, 'version' => 2, 'type' => 'message', 'payload' => ['id' => 'K1', 'source' => '919876543210', 'type' => 'text',
            'payload' => ['text' => 'hi'], 'sender' => ['phone' => '919876543210', 'name' => 'A']]];

        $this->assertStringContainsString('/api/webhooks/whatsapp/gupshup/' . $this->svc->webhookToken(), $this->svc->webhookUrl($this->svc->provider('gupshup')));
        $this->hook($payload, viaPath: true)->assertOk();
        $payload['payload']['id'] = 'K2';
        $this->hook($payload, viaPath: false)->assertOk();
        $this->postJson('/api/webhooks/whatsapp/gupshup/wrong-secret', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/whatsapp/gupshup', $payload)->assertForbidden();
    }

    public function test_a_customer_message_gets_an_assistant_reply_through_gupshup(): void
    {
        Http::fake(['api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'reply-1'])]);
        User::factory()->create(['phone' => '9876543210']);

        $this->hook(['app' => 'A', 'timestamp' => 1, 'version' => 2, 'type' => 'message', 'payload' => ['id' => 'H1', 'source' => '919876543210', 'type' => 'text',
            'payload' => ['text' => 'Hi'], 'sender' => ['phone' => '919876543210', 'name' => 'Asha']]])->assertOk();

        $reply = WhatsAppMessage::where('direction', 'out')->where('event', 'bot')->first();
        $this->assertNotNull($reply);
        $this->assertStringContainsString('Track my order', $reply->body);
        $this->assertSame('reply-1', $reply->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.gupshup.io/wa/api/v1/msg' && str_contains(urldecode($r->body()), 'destination=919876543210'));
    }

    public function test_the_callback_url_passes_gupshups_save_check_before_whatsapp_is_switched_on(): void
    {
        AppSetting::setValue('whatsapp_enabled', '0');
        $token = $this->svc->webhookToken();

        // Gupshup tests the URL when you press save: a POST (and possibly a GET) with the secret must answer 200.
        $this->postJson("/api/webhooks/whatsapp/gupshup/{$token}", ['type' => 'ping'])->assertOk()->assertJsonPath('success', true);
        $this->getJson("/api/webhooks/whatsapp/gupshup/{$token}")->assertOk();
        $this->assertSame(0, WhatsAppMessage::count());                          // and nothing is stored

        // a wrong or missing secret, or a different provider, still looks like a missing page
        $this->postJson('/api/webhooks/whatsapp/gupshup/wrong', ['type' => 'ping'])->assertNotFound();
        $this->postJson('/api/webhooks/whatsapp/gupshup', ['type' => 'ping'])->assertNotFound();
        $this->postJson("/api/webhooks/whatsapp/interakt/{$token}", [])->assertOk();   // unsigned provider with the right secret is just acknowledged
        $this->postJson('/api/webhooks/whatsapp/meta', [])->assertNotFound();           // signed providers never answer without a signature
        $this->postJson('/api/webhooks/whatsapp/nonsense/' . $token, [])->assertNotFound();
    }

    public function test_meta_format_v3_payloads_are_understood_too(): void
    {
        $out = WhatsAppMessage::create(['direction' => 'out', 'provider' => 'gupshup', 'phone' => '+919876543210', 'status' => 'sent',
            'provider_message_id' => 'gs-uuid-1']);

        $this->hook(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Asha']]],
            'messages' => [['id' => 'wamid.V3IN', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'Hi from v3']]],
        ]]]]]])->assertOk()->assertJsonPath('messages', 1);
        $this->assertSame('Hi from v3', WhatsAppMessage::where('provider_message_id', 'wamid.V3IN')->value('body'));
        $this->assertSame('Asha', WhatsAppContact::where('phone', '+919876543210')->value('profile_name'));

        $this->hook(['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [['field' => 'messages', 'value' => [
            'statuses' => [['id' => 'wamid.OUT1', 'gs_id' => 'gs-uuid-1', 'status' => 'delivered', 'recipient_id' => '919876543210']],
        ]]]]]])->assertOk()->assertJsonPath('statuses', 1);
        $this->assertSame('delivered', $out->fresh()->status);
    }
}
