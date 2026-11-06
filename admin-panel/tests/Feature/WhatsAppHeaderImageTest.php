<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Templates approved with an IMAGE header must be sent with the picture (else "(#2012) Format mismatch"). */
class WhatsAppHeaderImageTest extends TestCase
{
    use RefreshDatabase;

    private const IMG = 'https://cdn.example.com/promo.jpg';

    public function test_gupshup_sends_the_header_image_with_the_template(): void
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        foreach (['api_key' => 'k', 'app_name' => 'yumma', 'source_number' => '919000000000'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        Http::fake(['*' => Http::response(['status' => 'submitted', 'messageId' => 'g1'])]);
        $service = app(WhatsAppService::class);

        $service->sendRawTemplate('9876543210', 'tpl-1', ['Asha'], 'en', self::IMG);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'template/msg')
            && json_decode($r['message'], true) === ['type' => 'image', 'image' => ['link' => self::IMG]]);

        $service->sendRawTemplate('9876543211', 'tpl-1', ['Asha']);             // no picture: nothing extra is sent
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'template/msg') && ! isset($r['message']));
    }

    public function test_meta_puts_the_image_in_a_header_component(): void
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v'] as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['*' => Http::response(['messages' => [['id' => 'w1']]])]);

        app(WhatsAppService::class)->sendRawTemplate('9876543210', 'promo', ['Asha'], 'en', self::IMG);
        Http::assertSent(function (HttpRequest $r) {
            $c = $r['template']['components'] ?? [];

            return ($c[0]['type'] ?? null) === 'header' && $c[0]['parameters'][0]['image']['link'] === self::IMG && ($c[1]['type'] ?? null) === 'body';
        });
    }
}
