<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Restaurant and dish pictures inside the WhatsApp ordering chat. */
class WhatsAppImagesTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = 'https://cdn.example.com/menu/paneer.jpg';
    private const COVER = 'https://cdn.example.com/restaurants/spice-hub.jpg';

    private function configure(string $provider, array $fields): WhatsAppService
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', $provider);
        foreach ($fields as $k => $v) {
            AppSetting::setValue("whatsapp_{$provider}_{$k}", $v);
        }
        WhatsAppContact::updateOrCreate(['phone' => '+919876543210'], ['last_inbound_at' => now(), 'opted_in' => true]);

        return app(WhatsAppService::class);
    }

    private const META = ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 's', 'verify_token' => 'v'];

    // ---- Per provider -----------------------------------------------------------------------------------

    public function test_meta_twilio_and_gupshup_send_a_real_picture_with_the_caption(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
            'api.twilio.com/*' => Http::response(['sid' => 'SM1']),
            'api.gupshup.io/*' => Http::response(['status' => 'submitted', 'messageId' => 'gs-1']),
        ]);

        $this->assertSame('sent', $this->configure('meta', self::META)->sendImage('9876543210', self::PHOTO, 'Paneer Tikka · ₹220')->status);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com') && $r['type'] === 'image'
            && $r['image']['link'] === self::PHOTO && $r['image']['caption'] === 'Paneer Tikka · ₹220');

        $this->configure('twilio', ['account_sid' => 'AC1', 'auth_token' => 'tok', 'from_number' => '+14155238886'])->sendImage('9876543210', self::PHOTO, 'Paneer Tikka');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.twilio.com') && str_contains($r->body(), 'MediaUrl=') && str_contains($r->body(), 'Body=Paneer'));

        $m = $this->configure('gupshup', ['api_key' => 'k', 'app_name' => 'App', 'source_number' => '917834811114'])->sendImage('9876543210', self::PHOTO, 'Paneer Tikka');
        $this->assertSame('sent', $m->status);
        $this->assertSame('image', $m->type);
        Http::assertSent(function (HttpRequest $r) {
            $body = urldecode($r->body());

            return $r->url() === 'https://api.gupshup.io/wa/api/v1/msg' && str_contains($body, '"type":"image"')
                && str_contains($body, '"originalUrl":"' . self::PHOTO . '"') && str_contains($body, '"caption":"Paneer Tikka"');
        });
    }

    public function test_providers_without_picture_support_or_with_pictures_off_send_the_caption_as_text(): void
    {
        Http::fake(['api.interakt.ai/*' => Http::response(['result' => true, 'id' => 'ik-1']), 'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'w']]])]);

        $m = $this->configure('interakt', ['api_key' => 'abc'])->sendImage('9876543210', self::PHOTO, 'Paneer Tikka · ₹220');
        $this->assertSame('text', $m->type);                                  // Interakt: text fallback
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'interakt.ai') && ($r['type'] ?? null) === 'Text');

        $svc = $this->configure('meta', self::META);
        AppSetting::setValue('whatsapp_send_images', '0');                     // admin turned pictures off
        $this->assertSame('text', $svc->sendImage('9876543210', self::PHOTO, 'No picture please')->type);

        AppSetting::setValue('whatsapp_send_images', '1');
        $this->assertSame('text', $svc->sendImage('9876543210', null, 'Dish with no photo')->type);   // nothing to show
    }

    public function test_a_rejected_picture_falls_back_to_text_but_window_rules_still_apply(): void
    {
        $svc = $this->configure('meta', self::META);
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Media upload error']], 400)       // the picture is refused ...
            ->push(['messages' => [['id' => 'wamid.t']]])]);                     // ... so the caption goes as text

        $m = $svc->sendImage('9876543210', self::PHOTO, 'Paneer Tikka');
        $this->assertSame('text', $m->type);
        $this->assertSame('sent', $m->status);
        $this->assertSame(1, WhatsAppMessage::where('type', 'image')->where('status', 'failed')->count());   // the failed attempt is logged

        // outside the 24 hour window nothing is sent at all (and no text fallback either)
        WhatsAppContact::where('phone', '+919876543210')->update(['last_inbound_at' => now()->subDays(2)]);
        Http::fake();
        $closed = $svc->sendImage('9876543210', self::PHOTO, 'Late');
        $this->assertSame('failed', $closed->status);
        Http::assertNothingSent();
    }

    // ---- In the ordering chat ---------------------------------------------------------------------------------

    private function chatSetup(): array
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'meta');
        foreach (self::META as $k => $v) {
            AppSetting::setValue("whatsapp_meta_{$k}", $v);
        }
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.' . uniqid()]]])]);
        Role::findOrCreate('customer', 'web');
        User::factory()->create(['name' => 'Asha Verma', 'phone' => '9876543210'])->assignRole('customer');

        $restaurant = Restaurant::create([
            'owner_id' => User::factory()->create(['phone' => '9222222222'])->id, 'name' => 'Spice Hub', 'slug' => 'spice-hub', 'address' => 'Main Street', 'city' => 'Kolkata',
            'state' => 'WB', 'pincode' => '700001', 'latitude' => 22.5726, 'longitude' => 88.3639, 'phone' => '9000000001', 'email' => 'sh@example.com', 'cuisine' => ['North Indian', 'Chinese'],
            'is_open' => true, 'is_pure_veg' => false, 'min_order_amount' => 0, 'delivery_fee' => 0, 'delivery_time' => 30, 'delivery_radius' => 8, 'rating' => 4.5,
            'total_ratings' => 3, 'is_featured' => false, 'is_verified' => true, 'cover_image' => self::COVER,
        ]);
        $paneer = MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Paneer Tikka', 'price' => 220, 'is_available' => true, 'is_veg' => true, 'is_bestseller' => true,
            'description' => 'Smoky cottage cheese cubes marinated in yoghurt and spices.', 'images' => [self::PHOTO]]);
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Plain Naan', 'price' => 40, 'is_available' => true, 'is_veg' => true]);   // no photo

        return [$restaurant, $paneer];
    }

    private function say(string $text, string $type = 'text', ?array $location = null): void
    {
        $message = ['id' => 'wamid.' . uniqid(), 'from' => '919876543210', 'type' => $type];
        $type === 'location' ? $message['location'] = $location : $message['text'] = ['body' => $text];
        $body = json_encode(['entry' => [['changes' => [['value' => ['contacts' => [['wa_id' => '919876543210', 'profile' => ['name' => 'Asha']]], 'messages' => [$message]]]]]]]);
        $this->call('POST', '/api/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 's'), 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
    }

    public function test_the_restaurant_photo_dish_photos_and_add_to_cart_pictures_appear_during_an_order(): void
    {
        $this->chatSetup();

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');                                                       // pick Spice Hub

        // the restaurant's cover picture, captioned with rating, cuisines and time, then the menu text
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com') && ($r['type'] ?? null) === 'image'
            && $r['image']['link'] === self::COVER && str_contains($r['image']['caption'], 'Spice Hub') && str_contains($r['image']['caption'], '4.5') && str_contains($r['image']['caption'], 'North Indian'));
        $this->assertStringContainsString('PHOTO <number>', WhatsAppMessage::where('event', 'bot')->latest('id')->value('body'));

        // PHOTO 1 -> the dish picture with price, diet label and description
        $this->say('photo 1');
        Http::assertSent(fn (HttpRequest $r) => ($r['type'] ?? null) === 'image' && $r['image']['link'] === self::PHOTO
            && str_contains($r['image']['caption'], 'Paneer Tikka') && str_contains($r['image']['caption'], '220') && str_contains($r['image']['caption'], 'Smoky cottage cheese'));

        // a dish without a photo is explained in text, never an error
        $this->say('photo 2');
        $last = WhatsAppMessage::where('event', 'bot')->latest('id')->first();
        $this->assertSame('text', $last->type);
        $this->assertStringContainsString('Plain Naan', $last->body);

        // adding a dish confirms with its picture
        $imagesBefore = WhatsAppMessage::where('type', 'image')->count();
        $this->say('1x2');
        $this->assertSame($imagesBefore + 1, WhatsAppMessage::where('type', 'image')->count());
        $this->assertStringContainsString('Added 2 × Paneer Tikka', WhatsAppMessage::where('type', 'image')->latest('id')->value('body'));
    }

    public function test_the_admin_switch_turns_chat_pictures_off(): void
    {
        $this->chatSetup();
        AppSetting::setValue('whatsapp_send_images', '0');

        $this->say('3');
        $this->say('', 'location', ['latitude' => 22.573, 'longitude' => 88.364]);
        $this->say('1');
        $this->say('photo 1');

        $this->assertSame(0, WhatsAppMessage::where('type', 'image')->count());
        $this->assertStringContainsString('Paneer Tikka', WhatsAppMessage::where('event', 'bot')->latest('id')->value('body'));
    }
}
