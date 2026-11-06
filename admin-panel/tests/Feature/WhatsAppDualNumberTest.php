<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Gupshup for notifications/templates, a second (Meta) number for the interactive customer chats. */
class WhatsAppDualNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        AppSetting::setValue('whatsapp_chat_provider', 'meta');
        foreach (['api_key' => 'k', 'app_name' => 'yumma', 'source_number' => '919000000000'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        foreach (['phone_number_id' => '555', 'access_token' => 't', 'app_secret' => 'appsecret', 'verify_token' => 'v', 'catalog_id' => 'CAT1'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.1']], 'status' => 'submitted', 'messageId' => 'g1'])]);
    }

    private function metaWebhook(string $from, string $text): void
    {
        $body = json_encode(['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => $from, 'profile' => ['name' => 'Asha']]],
            'messages' => [['id' => 'wamid.' . uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'appsecret'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();
    }

    public function test_the_second_number_is_only_used_when_set_up_and_different(): void
    {
        $service = app(WhatsAppService::class);
        $this->assertSame('meta', $service->chatProvider()?->key());

        AppSetting::setValue('whatsapp_chat_provider', 'gupshup');              // same as the main one: no second number
        $this->assertNull($service->chatProvider());

        AppSetting::setValue('whatsapp_chat_provider', 'meta');
        AppSetting::setValue('whatsapp_meta_access_token', '');                  // not fully set up
        $this->assertNull($service->chatProvider());
    }

    public function test_replies_go_back_through_the_number_the_customer_wrote_to_while_templates_use_the_main_one(): void
    {
        $service = app(WhatsAppService::class);

        $this->metaWebhook('919876543210', 'hi');                                // the customer writes to the Meta number
        $this->assertSame('meta', WhatsAppContact::where('phone', app(WhatsAppService::class)->normalize('919876543210'))->value('channel'));
        $this->assertSame('meta', $service->providerFor('919876543210')->key());
        $this->assertSame('gupshup', $service->providerFor('919876543210', 'template')->key());

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com') && str_contains($r->url(), '/555/messages'));
        $this->assertSame('meta', \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->value('provider'));

        // a customer who never wrote to the Meta number stays on the main provider
        $this->assertSame('gupshup', $service->providerFor('919811111111')->key());
        $service->sendText('919811111111', 'hello', 'bot');
        $this->assertSame('gupshup', \App\Models\WhatsAppMessage::where('phone', $service->normalize('919811111111'))->value('provider'));
    }

    public function test_the_catalog_is_available_to_meta_chats_even_when_the_main_provider_cannot_show_one(): void
    {
        AppSetting::setValue('whatsapp_catalog_enabled', '1');
        $service = app(WhatsAppService::class);
        WhatsAppContact::create(['phone' => $service->normalize('919876543210'), 'opted_in' => true, 'channel' => 'meta']);

        $this->assertTrue($service->catalogEnabled());                           // the sync can run
        $this->assertTrue($service->catalogEnabled('919876543210'));             // a Meta chat can use it
        $this->assertFalse($service->catalogEnabled('919811111111'));            // a Gupshup chat falls back to the text menu
        $this->assertSame('meta', $service->catalogProvider()->key());
    }

    public function test_the_second_numbers_webhook_is_accepted_and_the_setting_can_be_saved(): void
    {
        $this->get('/api/webhooks/whatsapp/meta?hub_mode=subscribe&hub_verify_token=v&hub_challenge=77')->assertOk()->assertSee('77');

        $admin = \App\Models\User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);
        $this->get(route('admin.whatsapp.settings'))->assertOk()->assertSee('Second number for ordering chats');
        $this->post(route('admin.whatsapp.save'), ['whatsapp_provider' => 'gupshup', 'whatsapp_chat_provider' => 'meta', 'whatsapp_enabled' => 1])->assertSessionHas('success');
        $this->assertSame('meta', AppSetting::getValue('whatsapp_chat_provider'));
        $this->post(route('admin.whatsapp.save'), ['whatsapp_provider' => 'meta', 'whatsapp_chat_provider' => 'meta', 'whatsapp_enabled' => 1]);
        $this->assertSame('', (string) AppSetting::getValue('whatsapp_chat_provider'));       // same as the main one is ignored
    }
}
