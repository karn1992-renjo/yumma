<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\WhatsAppBroadcastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Header picture upload, and limiting any audience to chosen roles. */
class WhatsAppBroadcastUploadAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->withoutMiddleware(\App\Http\Middleware\EnsureAdminMfa::class)->actingAs($admin);
    }

    private function user(string $role, string $name, string $phone): User
    {
        $u = User::factory()->create(['name' => $name, 'phone' => $phone]);
        $u->assignRole(Role::findOrCreate($role, 'web'));

        return $u;
    }

    private function recipients(string $audience, array $roles = []): array
    {
        $b = WhatsAppBroadcast::create(['title' => 'T', 'template' => 'tpl', 'params' => [], 'audience' => $audience, 'roles' => $roles ?: null]);
        app(WhatsAppBroadcastService::class)->start($b);

        return $b->recipients()->pluck('first_name', 'phone')->all();
    }

    public function test_an_uploaded_picture_becomes_the_broadcast_header_image(): void
    {
        Storage::fake('public');
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        foreach (['api_key' => 'k', 'app_name' => 'yumma', 'source_number' => '919000000000'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        WhatsAppContact::create(['phone' => '+919000000001', 'opted_in' => true]);
        $this->admin();

        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()->assertSee('type="file"', false)->assertSee('multipart/form-data', false);
        $this->post(route('admin.whatsapp.broadcasts.store'), [
            'title' => 'Promo', 'template' => 'promo', 'audience' => 'contacts', 'consent' => '1',
            'header_image_file' => UploadedFile::fake()->image('promo.jpg', 800, 400),
        ])->assertSessionHas('success');

        $this->assertMatchesRegularExpression('#^https?://.+/whatsapp/.+\.jpe?g$#', (string) WhatsAppBroadcast::value('header_image'));
    }

    public function test_all_users_audience_and_role_limit_work_with_every_audience(): void
    {
        $this->user('customer', 'Asha Verma', '9876543210');
        $this->user('restaurant_owner', 'Ravi Kumar', '9811111111');
        $dev = $this->user('delivery_partner', 'Dev Singh', '9833333333');
        WhatsAppContact::create(['phone' => '+919999999999', 'opted_in' => true, 'profile_name' => 'Stranger']);   // chatted, no account

        $this->assertSame(['+919876543210' => 'Asha', '+919811111111' => 'Ravi', '+919833333333' => 'Dev'], $this->recipients('all_users'));
        $this->assertSame(['+919811111111' => 'Ravi', '+919833333333' => 'Dev'], $this->recipients('all_users', ['restaurant_owner', 'delivery_partner']));

        // "everyone who chatted" limited to a role: the stranger without an account is skipped
        $contact = WhatsAppContact::create(['phone' => '+919833333333', 'opted_in' => true, 'user_id' => $dev->id]);
        $this->assertSame(['+919833333333' => 'Dev'], $this->recipients('contacts', ['delivery_partner']));
        $this->assertCount(2, $this->recipients('contacts'));
    }

    public function test_the_form_shows_the_role_checkboxes_and_saves_them(): void
    {
        AppSetting::setValue('whatsapp_enabled', '1');
        AppSetting::setValue('whatsapp_provider', 'gupshup');
        foreach (['api_key' => 'k', 'app_name' => 'yumma', 'source_number' => '919000000000'] as $k => $v) {
            AppSetting::setValue("whatsapp_gupshup_{$k}", $v);
        }
        $this->user('delivery_partner', 'Dev Singh', '9833333333');
        $this->admin();

        $this->get(route('admin.whatsapp.broadcasts'))->assertOk()->assertSee('Limit to roles')->assertSee('Delivery partners (drivers)')->assertSee('Everyone: all users');
        $this->post(route('admin.whatsapp.broadcasts.store'), ['title' => 'Drivers', 'template' => 'x', 'audience' => 'all_users', 'consent' => '1', 'roles' => ['delivery_partner']])->assertSessionHas('success');
        $this->assertSame(['delivery_partner'], WhatsAppBroadcast::latest('id')->first()->roles);
    }
}
